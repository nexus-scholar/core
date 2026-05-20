<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\FullTextArtifactType;

final readonly class FullTextSourceCandidate
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $url,
        public FullTextArtifactType $artifactType = FullTextArtifactType::PDF,
        public array $metadata = [],
    ) {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Full-text source candidate URL must be valid.');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Full-text source candidate URL must use HTTP or HTTPS.');
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function pdf(string $url, array $metadata = []): self
    {
        return new self($url, FullTextArtifactType::PDF, $metadata);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function xml(string $url, array $metadata = []): self
    {
        return new self($url, FullTextArtifactType::XML, $metadata);
    }

    public function isPdf(): bool
    {
        return $this->artifactType === FullTextArtifactType::PDF;
    }
}

