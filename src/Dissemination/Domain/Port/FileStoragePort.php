<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

interface FileStoragePort
{
    /**
     * Save content to a file and return the unique path/identifier.
     */
    public function store(string $filename, string $content): string;

    /**
     * Retrieve the raw content of a file.
     */
    public function get(string $path): string;

    /**
     * Delete a file.
     */
    public function delete(string $path): void;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Get a public URL for the file if supported.
     */
    public function url(string $path): ?string;
}
