<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Http;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;

final class GuzzleHttpClient implements HttpClientPort
{
    public function __construct(
        private readonly Client $guzzle,
    ) {}

    /**
     * Factory method — uses composer/ca-bundle for SSL verification.
     * NEVER commit a cacert.pem file (known bug #5).
     */
    public static function create(int $timeoutSeconds = 30): self
    {
        $client = new Client([
            'timeout' => $timeoutSeconds,
            'verify'  => CaBundle::getSystemCaRootBundlePath(),
        ]);

        return new self($client);
    }

    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($headers !== []) {
            $options['headers'] = $headers;
        }

        $options = array_merge($options, ['http_errors' => false]);

        try {
            $guzzleResponse = $this->guzzle->get($url, $options);
            $rawBody        = (string) $guzzleResponse->getBody();
            $statusCode     = $guzzleResponse->getStatusCode();

            // Attempt JSON decode; fall back to empty array for non-JSON bodies
            $body = [];

            if (trim($rawBody) !== '') {
                $decoded = json_decode($rawBody, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decoded;
                }
            }

            $responseHeaders = [];

            foreach ($guzzleResponse->getHeaders() as $name => $values) {
                $responseHeaders[$name] = $values;
            }

            return new HttpResponse(
                statusCode: $statusCode,
                body:       $body ?? [],
                rawBody:    $rawBody,
                headers:    $responseHeaders,
            );
        } catch (GuzzleException $e) {
            throw new ProviderUnavailable(
                providerAlias: 'http',
                reason:        $e->getMessage(),
                previous:      $e,
            );
        }
    }
}
