<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
use Nexus\Shared\Domain\ScholarlyWork;

final readonly class PmcOaiFullTextSource implements FullTextCandidateSourcePort
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
        $pmcid = WorkIdentifierExtractor::pmcid($work);
        $pmcidNumber = WorkIdentifierExtractor::pmcidNumber($work);

        if ($pmcid === null || $pmcidNumber === null || ! $this->supports($work)) {
            return null;
        }

        $query = [
            'verb' => 'GetRecord',
            'identifier' => 'oai:pubmedcentral.nih.gov:'.$pmcidNumber,
            'metadataPrefix' => 'pmc',
        ];

        $response = $this->client->get(
            rtrim($this->client->config()->baseUrl, '/').'/',
            $query,
            [
                'Accept' => 'application/xml',
                'Accept-Encoding' => 'gzip, deflate',
            ],
        );

        if (! $response->ok() || ! $this->containsReusableFullText($response->rawBody)) {
            return null;
        }

        return FullTextSourceCandidate::xml(
            $this->urlWithQuery(rtrim($this->client->config()->baseUrl, '/').'/', $query),
            [
                'source' => 'pmc',
                'pmcid' => $pmcid,
                'oai_identifier' => $query['identifier'],
                'metadata_prefix' => 'pmc',
                'license' => $this->licenseFromXml($response->rawBody),
                'reusable' => true,
            ],
        );
    }

    public function alias(): string
    {
        return 'pmc';
    }

    public function supports(ScholarlyWork $work): bool
    {
        $config = $this->client->config();

        return $config->enabled && $config->preferXml && WorkIdentifierExtractor::pmcid($work) !== null;
    }

    private function containsReusableFullText(string $body): bool
    {
        $trimmed = trim($body);

        return $trimmed !== ''
            && str_contains($trimmed, '<metadata')
            && ! str_contains($trimmed, '<error');
    }

    private function licenseFromXml(string $body): ?string
    {
        if (preg_match('/<license\b[^>]*(?:xlink:href|href)="([^"]+)"/i', $body, $matches) !== 1) {
            return null;
        }

        return html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1);
    }

    /**
     * @param  array<string, string>  $query
     */
    private function urlWithQuery(string $url, array $query): string
    {
        return $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
