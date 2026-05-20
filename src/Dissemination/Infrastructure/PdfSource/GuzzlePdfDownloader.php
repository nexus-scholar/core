<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use GuzzleHttp\Client;
use Nexus\Dissemination\Domain\Port\PdfDownloaderPort;
use RuntimeException;

final class GuzzlePdfDownloader implements PdfDownloaderPort
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

    public function download(string $url): \Nexus\Dissemination\Domain\Port\DownloadResult
    {
        try {
            $response = $this->client->get($url);

            return new \Nexus\Dissemination\Domain\Port\DownloadResult(
                (string) $response->getBody(),
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type') ?: null,
            );
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            throw new RuntimeException(
                "Failed to download PDF from {$url}: HTTP {$e->getResponse()->getStatusCode()}", 
                $e->getResponse()->getStatusCode(), 
                $e
            );
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to download PDF from {$url}: {$e->getMessage()}", 0, $e);
        }
    }
}
