<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

interface StreamingFileStoragePort
{
    /**
     * Store a local file without requiring the caller to load it into memory.
     */
    public function storeFile(string $filename, string $sourcePath): string;
}
