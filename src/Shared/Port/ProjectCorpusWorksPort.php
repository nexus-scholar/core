<?php

declare(strict_types=1);

namespace Nexus\Shared\Port;

interface ProjectCorpusWorksPort
{
    /**
     * Return authoritative internal scholarly_works IDs for a project.
     *
     * Locked projects are backed by the latest immutable corpus snapshot.
     * Draft projects may still infer membership from current query/work links.
     *
     * @param  list<string>  $queryIds
     * @return list<string>
     */
    public function workIds(string $projectId, array $queryIds = []): array;
}
