<?php

declare(strict_types=1);

namespace Nexus\Search\Domain;

/**
 * Value object representing progress of a search operation for a specific provider.
 */
final class ProviderProgress
{
    public function __construct(
        public readonly int     $totalRaw,
        public readonly int     $totalUnique,
        public readonly int     $durationMs,
        public readonly ?string $errorMessage = null,
        public readonly array   $metadata = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            totalRaw:     $data['total_raw'] ?? 0,
            totalUnique:  $data['total_unique'] ?? 0,
            durationMs:   $data['duration_ms'] ?? 0,
            errorMessage: $data['error_message'] ?? null,
            metadata:     $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'total_raw'     => $this->totalRaw,
            'total_unique'  => $this->totalUnique,
            'duration_ms'   => $this->durationMs,
            'error_message' => $this->errorMessage,
            'metadata'      => $this->metadata,
        ];
    }
}
