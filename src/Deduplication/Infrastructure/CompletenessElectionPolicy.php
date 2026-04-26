<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

/**
 * Elects the representative based on completeness score + provider priority.
 *
 * Score = completenessScore() + providerPriority[$sourceProvider]
 * Tie-break: prefer DOI presence, then earlier retrieval time.
 *
 * providerPriority is INJECTED from config — never hardcoded (known bug #7).
 */
final class CompletenessElectionPolicy implements RepresentativeElectionPort
{
    private const DEFAULT_PRIORITY = [
        'openalex'        => 5,
        'crossref'        => 4,
        'semantic_scholar' => 3,
        'arxiv'           => 2,
        'pubmed'          => 2,
        'ieee'            => 1,
        'doaj'            => 1,
    ];

    public function __construct(
        private readonly array $providerPriority = self::DEFAULT_PRIORITY,
    ) {}

    public function elect(array $members): ScholarlyWork
    {
        if ($members === []) {
            throw new \InvalidArgumentException('Cannot elect from an empty member list.');
        }

        usort($members, function (ScholarlyWork $a, ScholarlyWork $b): int {
            $scoreA = $this->totalScore($a);
            $scoreB = $this->totalScore($b);

            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA; // higher score first
            }

            // Tie-break 1: prefer DOI
            $hasDOI_A = $a->ids()->findByNamespace(WorkIdNamespace::DOI) !== null;
            $hasDOI_B = $b->ids()->findByNamespace(WorkIdNamespace::DOI) !== null;

            if ($hasDOI_A !== $hasDOI_B) {
                return $hasDOI_A ? -1 : 1;
            }

            // Tie-break 2: earlier retrieval time
            return $a->retrievedAt() <=> $b->retrievedAt();
        });

        return $members[0];
    }

    private function totalScore(ScholarlyWork $work): int
    {
        $priority = $this->providerPriority[$work->sourceProvider()] ?? 0;

        return $work->completenessScore() + $priority;
    }
}
