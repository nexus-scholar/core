<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use Illuminate\Support\Facades\Storage;
use Nexus\Dissemination\Domain\Port\FileStoragePort;

class LaravelFileStorage implements FileStoragePort
{
    public function __construct(private string $disk = 'public') {}

    public function store(string $filename, string $content): string
    {
        Storage::disk($this->disk)->put($filename, $content);

        return $filename;
    }

    public function get(string $path): string
    {
        return Storage::disk($this->disk)->get($path) ?? '';
    }

    public function delete(string $path): void
    {
        Storage::disk($this->disk)->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    public function url(string $path): ?string
    {
        return Storage::disk($this->disk)->url($path);
    }
}
