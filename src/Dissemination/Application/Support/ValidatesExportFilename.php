<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\Support;

use InvalidArgumentException;

trait ValidatesExportFilename
{
    private function assertFilenameMatchesExtension(string $filename, string $extension, string $format): void
    {
        $actual = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if ($actual === strtolower($extension)) {
            return;
        }

        throw new InvalidArgumentException(
            "Export filename extension must match format {$format} (.{$extension}): {$filename}",
        );
    }
}
