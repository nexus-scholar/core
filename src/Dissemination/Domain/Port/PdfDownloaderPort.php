<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

interface PdfDownloaderPort
{
    /**
     * Downloads content from a URL.
     * Returns a DownloadResult containing content and status code.
     * Throws exception on failure.
     */
    public function download(string $url): DownloadResult;
}
