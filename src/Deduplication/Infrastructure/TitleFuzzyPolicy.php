<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Detects near-duplicate titles using sorted-list adjacent comparison.
 *
 * Algorithm:
 *   1. Normalize all titles via TitleNormalizer
 *   2. Sort by normalized title (lexicographic)
 *   3. Compare only adjacent pairs — titles with small edit distance
 *      cluster together after sorting, so expensive Levenshtein calls
 *      are avoided for clearly dissimilar pairs
 *   4. For adjacent pairs within maxYearGap: compute Unicode-safe fuzzyRatio
 *   5. Emit Duplicate if ratio >= threshold
 *
 * This is NOT O(n²) — sorting is O(n log n) and adjacent comparison is O(n·k)
 * where k is the average title length.
 */
final class TitleFuzzyPolicy implements DeduplicationPolicyPort
{
    public function __construct(
        private readonly TitleNormalizer $normalizer,
        private readonly int             $threshold  = 92,
        // 92 recommended over old default of 97 (better recall)
        private readonly int             $maxYearGap = 1,
    ) {}

    public function name(): string
    {
        return 'title_fuzzy';
    }

    public function detect(array $works): array
    {
        if (count($works) < 2) {
            return [];
        }

        // Build sorted list of [normalizedTitle, index]
        $indexed = [];

        foreach ($works as $i => $work) {
            $indexed[] = [
                'normalized' => $this->normalizer->normalize($work->title()),
                'index'      => $i,
            ];
        }

        usort($indexed, fn (array $a, array $b) => $a['normalized'] <=> $b['normalized']);

        $duplicates = [];
        $count      = count($indexed);

        for ($i = 0; $i < $count - 1; $i++) {
            $currNorm  = $indexed[$i]['normalized'];
            $currWork  = $works[$indexed[$i]['index']];
            $nextNorm  = $indexed[$i + 1]['normalized'];
            $nextWork  = $works[$indexed[$i + 1]['index']];

            if ($currNorm === '' || $nextNorm === '') {
                continue;
            }

            // Year gap filter
            $yearA = $currWork->year();
            $yearB = $nextWork->year();

            if ($yearA !== null && $yearB !== null
                && abs($yearA - $yearB) > $this->maxYearGap
            ) {
                continue;
            }

            $ratio = $this->normalizer->fuzzyRatio($currNorm, $nextNorm);

            if ($ratio < $this->threshold) {
                continue;
            }

            $primaryId   = $currWork->primaryId();
            $secondaryId = $nextWork->primaryId();

            if ($primaryId === null || $secondaryId === null) {
                continue;
            }

            $duplicates[] = new Duplicate(
                primaryId:   $primaryId,
                secondaryId: $secondaryId,
                reason:      DuplicateReason::TITLE_FUZZY,
                confidence:  round($ratio / 100, 2),
            );
        }

        return $duplicates;
    }
}
