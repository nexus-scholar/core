<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Infrastructure;

use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

/**
 * Detects duplicates by exact match in a specific namespace.
 * One instance per namespace (ARXIV, OPENALEX, S2, PUBMED).
 * O(n) — single pass with index.
 */
final class NamespaceMatchPolicy implements DeduplicationPolicyPort
{
    private static array $reasonMap = [
        'arxiv'    => DuplicateReason::ARXIV_MATCH,
        'openalex' => DuplicateReason::OPENALEX_MATCH,
        's2'       => DuplicateReason::S2_MATCH,
        'pubmed'   => DuplicateReason::PUBMED_MATCH,
    ];

    public function __construct(
        private readonly WorkIdNamespace $namespace,
    ) {}

    public function name(): string
    {
        return $this->namespace->value . '_match';
    }

    public function detect(array $works): array
    {
        /** @var array<string, ScholarlyWork> */
        $index      = [];
        $duplicates = [];

        foreach ($works as $work) {
            $id = $work->ids()->findByNamespace($this->namespace);

            if ($id === null) {
                continue;
            }

            $key = $id->value;

            if (isset($index[$key])) {
                $primaryWork = $index[$key];
                $primaryId   = $primaryWork->primaryId();
                $secondaryId = $work->primaryId();

                if ($primaryId === null || $secondaryId === null) {
                    continue;
                }

                $reason = self::$reasonMap[$this->namespace->value]
                    ?? DuplicateReason::FINGERPRINT;

                $duplicates[] = new Duplicate(
                    primaryId:   $primaryId,
                    secondaryId: $secondaryId,
                    reason:      $reason,
                    confidence:  1.0,
                );
            } else {
                $index[$key] = $work;
            }
        }

        return $duplicates;
    }
}
