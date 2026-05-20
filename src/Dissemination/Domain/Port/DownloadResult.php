<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

final readonly class DownloadResult
{
    public function __construct(
        public string  $content,
        public int     $statusCode,
        public ?string $contentType = null,
    ) {}
}
