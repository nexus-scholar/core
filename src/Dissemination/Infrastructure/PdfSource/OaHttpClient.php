<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;

final readonly class OaHttpClient
{
    public function __construct(
        private HttpClientPort $http,
        private RateLimiterPort $rateLimiter,
        private FullTextSourceConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $this->config->maxRetries; $attempt++) {
            $this->rateLimiter->waitForToken();

            try {
                $response = $this->http->get(
                    $url,
                    $query,
                    $headers,
                    $this->config->timeoutSeconds,
                );

                if (! $this->shouldRetry($response) || $attempt === $this->config->maxRetries) {
                    return $response;
                }
            } catch (ProviderUnavailable $error) {
                $lastError = $error;

                if ($attempt === $this->config->maxRetries) {
                    throw $error;
                }
            }
        }

        throw $lastError ?? new ProviderUnavailable($this->config->alias, 'HTTP request failed without a response.');
    }

    public function config(): FullTextSourceConfig
    {
        return $this->config;
    }

    private function shouldRetry(HttpResponse $response): bool
    {
        return $response->rateLimited() || $response->serverError();
    }
}

