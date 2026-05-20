<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
use Nexus\Search\Domain\ScholarlyWork;

final readonly class UnpaywallPdfSource implements FullTextCandidateSourcePort
{
    public function __construct(
        private OaHttpClient $client,
    ) {}

    public function resolve(ScholarlyWork $work): ?string
    {
        return $this->resolveCandidate($work)?->url;
    }

    public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate
    {
        $doi = WorkIdentifierExtractor::doi($work);
        if ($doi === null || ! $this->isConfigured()) {
            return null;
        }

        $config = $this->client->config();
        $response = $this->client->get(
            rtrim($config->baseUrl, '/') . '/' . rawurlencode($doi),
            ['email' => $config->email],
            ['Accept' => 'application/json'],
        );

        if (! $response->ok() || $response->body === []) {
            return null;
        }

        if (($response->body['is_oa'] ?? false) !== true || ($response->body['oa_status'] ?? null) === 'closed') {
            return null;
        }

        foreach ($this->pdfLocations($response->body) as $location) {
            $url = $this->validUrl($location['url_for_pdf'] ?? null);
            if ($url === null) {
                continue;
            }

            return FullTextSourceCandidate::pdf(
                $url,
                $this->metadata($doi, $response->body, $location),
            );
        }

        return null;
    }

    public function alias(): string
    {
        return 'unpaywall';
    }

    public function supports(ScholarlyWork $work): bool
    {
        return $this->isConfigured() && WorkIdentifierExtractor::doi($work) !== null;
    }

    public function isConfigured(): bool
    {
        $config = $this->client->config();

        return $config->enabled && $config->email !== null;
    }

    /**
     * @param array<string, mixed> $body
     * @return list<array<string, mixed>>
     */
    private function pdfLocations(array $body): array
    {
        $locations = [];

        if (isset($body['best_oa_location']) && is_array($body['best_oa_location'])) {
            $locations[] = $body['best_oa_location'];
        }

        if (isset($body['oa_locations']) && is_array($body['oa_locations'])) {
            foreach ($body['oa_locations'] as $location) {
                if (is_array($location)) {
                    $locations[] = $location;
                }
            }
        }

        return $locations;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $location
     * @return array<string, mixed>
     */
    private function metadata(string $doi, array $body, array $location): array
    {
        return [
            'source' => 'unpaywall',
            'doi' => $body['doi'] ?? $doi,
            'oa_status' => $body['oa_status'] ?? null,
            'is_oa' => $body['is_oa'] ?? null,
            'license' => $location['license'] ?? null,
            'host_type' => $location['host_type'] ?? null,
            'version' => $location['version'] ?? null,
            'url_for_landing_page' => $location['url_for_landing_page'] ?? null,
            'evidence' => $location['evidence'] ?? null,
        ];
    }

    private function validUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}

