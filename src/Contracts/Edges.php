<?php

declare(strict_types=1);

namespace JMac\Testing\PhpUnit\Tia\Contracts;

/**
 * Read-only view of the recorded graph, for resolvers that map a change onto
 * files the graph already knows (§4.3). A new partial is reached through the
 * templates that include it, a new migration through the earlier migrations
 * of the same table.
 */
interface Edges
{
    /**
     * @param  string  $sourceFile  Absolute or project-relative path.
     * @return list<string> project-relative test files with an edge to $sourceFile
     */
    public function testsLinkedTo(string $sourceFile): array;

    /**
     * Every test file the graph knows. A test it does not know runs anyway,
     * so this is every test a resolver may need to run for a change that
     * affects all of them, such as a config file.
     *
     * @return array<int, string> project-relative test files
     */
    public function allTestFiles(): array;
}
