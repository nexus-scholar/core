<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

final readonly class Bibliography
{
	public function __construct(
		public BibliographyFormat $format,
		public string             $content,
		public string             $filename,
		public \DateTimeImmutable $generatedAt,
	) {}

	public static function create(
		BibliographyFormat $format,
		string $content,
		string $filename,
		?\DateTimeImmutable $generatedAt = null,
	): self {
		return new self(
			format: $format,
			content: $content,
			filename: $filename,
			generatedAt: $generatedAt ?? new \DateTimeImmutable(),
		);
	}

	public function sizeBytes(): int
	{
		return strlen($this->content);
	}
}
