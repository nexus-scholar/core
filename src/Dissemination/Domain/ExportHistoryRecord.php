<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

use DateTimeImmutable;

final readonly class ExportHistoryRecord
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public ExportType $type,
        public string $format,
        public string $filename,
        public string $path,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $projectId = null,
        public ?string $corpusSliceId = null,
        public ?string $citationGraphId = null,
        public ?string $requestedBy = null,
        public array $metadata = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        ExportType $type,
        string $format,
        string $filename,
        string $path,
        string $mimeType,
        int $sizeBytes,
        ?string $projectId = null,
        ?string $corpusSliceId = null,
        ?string $citationGraphId = null,
        ?string $requestedBy = null,
        array $metadata = [],
        ?DateTimeImmutable $createdAt = null,
    ): self {
        return new self(
            id: self::uuid(),
            type: $type,
            format: $format,
            filename: $filename,
            path: $path,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
            projectId: $projectId,
            corpusSliceId: $corpusSliceId,
            citationGraphId: $citationGraphId,
            requestedBy: $requestedBy,
            metadata: $metadata,
            createdAt: $createdAt ?? new DateTimeImmutable(),
        );
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
