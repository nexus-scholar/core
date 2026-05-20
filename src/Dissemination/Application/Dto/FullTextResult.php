<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\Dto;

use Nexus\Dissemination\Domain\FullTextStatus;

final readonly class FullTextResult
{
    public function __construct(
        public FullTextStatus $status,
        public ?string        $filePath     = null,
        public ?string        $sourceAlias  = null,
        public ?string        $errorMessage = null,
        public ?int           $httpStatus   = null,
        public array          $metadata     = [],
    ) {}

    /**
     * @param array<string, mixed> $metadata
     */
    public static function success(string $filePath, string $sourceAlias, ?int $httpStatus = 200, array $metadata = []): self
    {
        return new self(
            status:      FullTextStatus::SUCCESS,
            filePath:    $filePath,
            sourceAlias: $sourceAlias,
            httpStatus:  $httpStatus,
            metadata:    $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function failure(
        string $errorMessage,
        ?string $sourceAlias = null,
        ?int $httpStatus = null,
        array $metadata = [],
    ): self {
        return new self(
            status:       FullTextStatus::FAILURE,
            sourceAlias:  $sourceAlias,
            errorMessage: $errorMessage,
            httpStatus:   $httpStatus,
            metadata:     $metadata,
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function skipped(string $reason, ?string $sourceAlias = null, array $metadata = []): self
    {
        return new self(
            status:       FullTextStatus::SKIPPED,
            sourceAlias:  $sourceAlias,
            errorMessage: $reason,
            metadata:     $metadata,
        );
    }
}
