<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Tests;

use JMac\Testing\PhpUnit\Tia\Tests\Support\TempGitRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Extension::bootstrap() writes the run summary to STDERR, which can't be
 * intercepted in-process, so this runs this package's own `phpunit` binary
 * against a throwaway project — the same approach as WarnCoversTargetingTest.
 * The wording itself is covered by TiaTest; this only checks when it's written.
 */
final class RunSummaryTest extends TestCase
{
    private TempGitRepository $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = TempGitRepository::create();
        $this->project->write('bootstrap.php', 'require '.var_export(dirname(__DIR__).'/vendor/autoload.php', true).";\n");
        $this->project->write('phpunit.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit bootstrap="bootstrap.php" colors="false">
                <testsuites>
                    <testsuite name="unit">
                        <directory>tests</directory>
                    </testsuite>
                </testsuites>
                <extensions>
                    <bootstrap class="JMac\Testing\PhpUnit\Tia\Extension">
                        <parameter name="storage" value="local"/>
                    </bootstrap>
                </extensions>
            </phpunit>
            XML);
        $this->project->write('tests/ExampleTest.php', <<<'PHP'
            <?php

            final class ExampleTest extends \PHPUnit\Framework\TestCase
            {
                public function test_it_passes(): void
                {
                    $this->assertTrue(true);
                }
            }
            PHP);
        $this->project->commit('init');
    }

    protected function tearDown(): void
    {
        $this->project->cleanup();

        parent::tearDown();
    }

    #[Test]
    public function it_writes_the_summary_before_the_run(): void
    {
        $this->assertStringContainsString(
            'phpunit-tia: inactive: PHPUNIT_TIA_FRESH=1 — baseline is being rebuilt this run.',
            $this->runProject([]),
        );
    }

    #[Test]
    public function it_says_tia_is_inactive_when_disabled(): void
    {
        $this->assertStringContainsString(
            'phpunit-tia: inactive: disabled via PHPUNIT_TIA=0.',
            $this->runProject(['PHPUNIT_TIA' => '0']),
        );
    }

    #[Test]
    public function it_says_tia_is_inactive_when_the_run_does_not_allow_skips(): void
    {
        $this->runProject([]);

        $this->assertStringContainsString(
            'phpunit-tia: 0 of 1 test files affected.',
            $this->runProject(['PHPUNIT_TIA_FRESH' => '0']),
        );
        $this->assertStringContainsString(
            "phpunit-tia: inactive: a skip would violate this run's fail-on-skipped/display-skipped policy — every test runs.",
            $this->runProject(['PHPUNIT_TIA_FRESH' => '0'], ['--fail-on-skipped']),
        );
    }

    #[Test]
    public function it_writes_no_summary_in_a_paratest_worker(): void
    {
        $output = $this->runProject(['PARATEST' => '1']);

        $this->assertStringNotContainsString('phpunit-tia: inactive:', $output);
        $this->assertStringNotContainsString('test files affected', $output);
    }

    /**
     * @param  array<string, string>  $environment
     * @param  list<string>  $arguments
     */
    private function runProject(array $environment, array $arguments = []): string
    {
        $process = new Process(
            [dirname(__DIR__).'/vendor/bin/phpunit', ...$arguments],
            $this->project->path(),
            ['PHPUNIT_TIA_FRESH' => '1', ...$environment],
        );
        $process->run();

        return $process->getErrorOutput();
    }
}
