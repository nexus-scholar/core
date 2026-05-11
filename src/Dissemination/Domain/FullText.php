<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

final readonly class FullText
{
	public function __construct(
		public FullTextStatus $status,
		public ?string        $filePath = null,
		public ?string        $sourceAlias = null,
		public ?string        $errorMessage = null,
		public ?int           $httpStatus = null,
	) {}

	public static function success(string $filePath, string $sourceAlias, ?int $httpStatus = 200): self
	{
		return new self(
			status: FullTextStatus::SUCCESS,
			filePath: $filePath,
			sourceAlias: $sourceAlias,
			httpStatus: $httpStatus,
		);
	}

	public static function failure(string $errorMessage, ?string $sourceAlias = null, ?int $httpStatus = null): self
	{
		return new self(
			status: FullTextStatus::FAILURE,
			sourceAlias: $sourceAlias,
			errorMessage: $errorMessage,
			httpStatus: $httpStatus,
		);
	}

	public static function skipped(string $reason): self
	{
		return new self(
			status: FullTextStatus::SKIPPED,
			errorMessage: $reason,
		);
	}

	public function isSuccess(): bool
	{
		return $this->status === FullTextStatus::SUCCESS;
	}
}
