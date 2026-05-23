<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

interface StreamingPdfDownloaderPort
{
    /**
     * Download a URL to a local temporary file and return the file result.
     * The caller owns cleanup of the returned file path.
     */
    public function downloadToFile(string $url): DownloadFileResult;
}
