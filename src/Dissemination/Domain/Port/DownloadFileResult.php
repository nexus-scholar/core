<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use InvalidArgumentException;

final readonly class DownloadFileResult
{
    public function __construct(
        public string $path,
        public int $statusCode,
        public ?string $contentType = null,
        public ?int $sizeBytes = null,
    ) {
        if (! is_file($this->path)) {
            throw new InvalidArgumentException(sprintf('Downloaded file does not exist: %s', $this->path));
        }
    }
}
