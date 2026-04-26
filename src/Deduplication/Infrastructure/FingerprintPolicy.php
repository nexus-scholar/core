<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Search\Domain\ScholarlyWork;

/**
 * Detects duplicates by content fingerprint.
 *
 * Fingerprint = md5(normalizedTitle[0:50] . ':' . normalizedFirstAuthorFamily . ':' . year)
 *
 * O(n) — one pass, one hash index.
 * Confidence: 0.90 (heuristic — not exact)
 */
final class FingerprintPolicy implements DeduplicationPolicyPort
{
    public function __construct(
        private readonly TitleNormalizer $normalizer,
    ) {}

    public function name(): string
    {
        return 'fingerprint';
    }

    public function detect(array $works): array
    {
        /** @var array<string, ScholarlyWork> */
        $index      = [];
        $duplicates = [];

        foreach ($works as $work) {
            $fp = $this->fingerprint($work);

            if ($fp === null) {
                continue;
            }

            if (isset($index[$fp])) {
                $primaryWork = $index[$fp];
                $primaryId   = $primaryWork->primaryId();
                $secondaryId = $work->primaryId();

                if ($primaryId === null || $secondaryId === null) {
                    continue;
                }

                $duplicates[] = new Duplicate(
                    primaryId:   $primaryId,
                    secondaryId: $secondaryId,
                    reason:      DuplicateReason::FINGERPRINT,
                    confidence:  0.90,
                );
            } else {
                $index[$fp] = $work;
            }
        }

        return $duplicates;
    }

    private function fingerprint(ScholarlyWork $work): ?string
    {
        $title  = $this->normalizer->normalize($work->title());
        $title  = mb_substr($title, 0, 50, 'UTF-8');

        $firstAuthorFamily = '';
        $firstAuthor = $work->authors()->first();

        if ($firstAuthor !== null) {
            $firstAuthorFamily = $this->normalizer->normalize($firstAuthor->familyName);
        }

        $year = $work->year();

        if ($title === '' && $firstAuthorFamily === '' && $year === null) {
            return null;
        }

        return md5($title . ':' . $firstAuthorFamily . ':' . ($year ?? ''));
    }
}
