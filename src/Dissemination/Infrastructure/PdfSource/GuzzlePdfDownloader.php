<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Nexus\Dissemination\Domain\Port\DownloadFileResult;
use Nexus\Dissemination\Domain\Port\DownloadResult;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use Nexus\Dissemination\Domain\Port\StreamingPdfDownloaderPort;
use RuntimeException;
use Throwable;

final class GuzzlePdfDownloader implements PdfDownloaderPort, StreamingPdfDownloaderPort
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'NexusScholar/1.0 (Systematic Literature Review toolkit)',
            ],
        ]);
    }

    public function download(string $url): DownloadResult
    {
        try {
            $response = $this->client->get($url);

            return new DownloadResult(
                (string) $response->getBody(),
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type') ?: null,
            );
        } catch (BadResponseException $e) {
            throw new RuntimeException(
                "Failed to download PDF from {$url}: HTTP {$e->getResponse()->getStatusCode()}",
                $e->getResponse()->getStatusCode(),
                $e
            );
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to download PDF from {$url}: {$e->getMessage()}", 0, $e);
        }
    }

    public function downloadToFile(string $url): DownloadFileResult
    {
        $path = tempnam(sys_get_temp_dir(), 'nexus_pdf_');
        if ($path === false) {
            throw new RuntimeException('Failed to create temporary file for PDF download.');
        }

        try {
            $response = $this->client->get($url, [
                'sink' => $path,
            ]);

            return new DownloadFileResult(
                $path,
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type') ?: null,
                filesize($path) ?: null,
            );
        } catch (BadResponseException $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw new RuntimeException(
                "Failed to download PDF from {$url}: HTTP {$e->getResponse()->getStatusCode()}",
                $e->getResponse()->getStatusCode(),
                $e,
            );
        } catch (Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }

            throw new RuntimeException("Failed to download PDF from {$url}: {$e->getMessage()}", 0, $e);
        }
    }
}
