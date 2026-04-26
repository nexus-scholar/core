<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

use Nexus\Shared\ValueObject\WorkId;

/**
 * Represents evidence that two works are duplicates.
 */
final class Duplicate
{
    public function __construct(
        public readonly WorkId          $primaryId,    // representative's primary ID
        public readonly WorkId          $secondaryId,  // duplicate's primary ID
        public readonly DuplicateReason $reason,
        public readonly float           $confidence,   // 0.0 – 1.0
    ) {}

    public function involves(WorkId $id): bool
    {
        return $this->primaryId->equals($id) || $this->secondaryId->equals($id);
    }

    public function isHighConfidence(): bool
    {
        return $this->confidence >= 0.95;
    }

    public function toArray(): array
    {
        return [
            'primaryId'   => $this->primaryId->toString(),
            'secondaryId' => $this->secondaryId->toString(),
            'reason'      => $this->reason->value,
            'confidence'  => $this->confidence,
        ];
    }
}
