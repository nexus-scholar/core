<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

/**
 * Detects duplicates by exact DOI match.
 * O(n) — one pass, one index. Never O(n²).
 */
final class DoiMatchPolicy implements DeduplicationPolicyPort
{
    public function name(): string
    {
        return 'doi_match';
    }

    public function detect(array $works): array
    {
        /** @var array<string, ScholarlyWork> $index doi_value => first work seen */
        $index      = [];
        $duplicates = [];

        foreach ($works as $work) {
            $doi = $work->ids()->findByNamespace(WorkIdNamespace::DOI);

            if ($doi === null) {
                continue;
            }

            $key = $doi->value; // already normalized by WorkId constructor

            if (isset($index[$key])) {
                $primaryWork = $index[$key];
                $primaryId   = $primaryWork->primaryId();
                $secondaryId = $work->primaryId();

                if ($primaryId === null || $secondaryId === null) {
                    continue;
                }

                $duplicates[] = new Duplicate(
                    primaryId:   $primaryId,
                    secondaryId: $secondaryId,
                    reason:      DuplicateReason::DOI_MATCH,
                    confidence:  1.0,
                );
            } else {
                $index[$key] = $work;
            }
        }

        return $duplicates;
    }
}
