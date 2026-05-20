<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextCandidateSourcePort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCandidate;
use Nexus\Search\Domain\ScholarlyWork;

final readonly class EuropePmcFullTextSource implements FullTextCandidateSourcePort
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
        if (! $this->supports($work)) {
            return null;
        }

        $doi = WorkIdentifierExtractor::doi($work);
        $pmcid = WorkIdentifierExtractor::pmcid($work);
        $response = $this->client->get(
            rtrim($this->client->config()->baseUrl, '/') . '/search',
            [
                'query' => $doi !== null ? 'DOI:"' . addcslashes($doi, '"\\') . '"' : 'EXT_ID:' . $pmcid,
                'resultType' => 'core',
                'format' => 'json',
                'pageSize' => 1,
            ],
            ['Accept' => 'application/json'],
        );

        if (! $response->ok()) {
            return null;
        }

        $record = $response->body['resultList']['result'][0] ?? null;
        if (! is_array($record)) {
            return null;
        }

        if ($this->client->config()->preferPdf) {
            $pdf = $this->pdfCandidate($record, $doi, $pmcid);
            if ($pdf !== null) {
                return $pdf;
            }
        }

        if (! $this->client->config()->preferXml) {
            return null;
        }

        $recordPmcid = WorkIdentifierExtractor::normalizePmcid($record['pmcid'] ?? $pmcid);
        if ($recordPmcid === null || ! $this->hasOpenFullTextSignal($record)) {
            return null;
        }

        return FullTextSourceCandidate::xml(
            rtrim($this->client->config()->baseUrl, '/') . '/' . rawurlencode($recordPmcid) . '/fullTextXML',
            $this->metadata($record, $doi, $recordPmcid, ['artifact_source' => 'fullTextXML']),
        );
    }

    public function alias(): string
    {
        return 'europe_pmc';
    }

    public function supports(ScholarlyWork $work): bool
    {
        $config = $this->client->config();

        return $config->enabled
            && (WorkIdentifierExtractor::doi($work) !== null || WorkIdentifierExtractor::pmcid($work) !== null);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function pdfCandidate(array $record, ?string $doi, ?string $pmcid): ?FullTextSourceCandidate
    {
        $urls = $record['fullTextUrlList']['fullTextUrl'] ?? [];
        if (! is_array($urls)) {
            return null;
        }

        foreach ($urls as $item) {
            if (! is_array($item) || ! $this->isOpenPdfUrl($item)) {
                continue;
            }

            $url = $this->validUrl($item['url'] ?? null);
            if ($url === null) {
                continue;
            }

            return FullTextSourceCandidate::pdf(
                $url,
                $this->metadata(
                    $record,
                    $doi,
                    WorkIdentifierExtractor::normalizePmcid($record['pmcid'] ?? $pmcid),
                    [
                        'availability' => $item['availability'] ?? null,
                        'availability_code' => $item['availabilityCode'] ?? null,
                        'document_style' => $item['documentStyle'] ?? null,
                        'site' => $item['site'] ?? null,
                    ],
                ),
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function isOpenPdfUrl(array $item): bool
    {
        $documentStyle = strtolower((string) ($item['documentStyle'] ?? ''));
        if (! str_contains($documentStyle, 'pdf')) {
            return false;
        }

        $availability = strtolower((string) ($item['availability'] ?? ''));
        $availabilityCode = strtolower((string) ($item['availabilityCode'] ?? ''));

        return $availability === ''
            || str_contains($availability, 'open')
            || str_contains($availability, 'free')
            || $availabilityCode === 'oa';
    }

    /**
     * @param array<string, mixed> $record
     */
    private function hasOpenFullTextSignal(array $record): bool
    {
        foreach (['isOpenAccess', 'inEPMC', 'hasPDF', 'hasTextMinedTerms'] as $key) {
            if (in_array($record[$key] ?? null, ['Y', 'y', true, 'true', '1', 1], true)) {
                return true;
            }
        }

        return isset($record['fullTextIdList']);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function metadata(array $record, ?string $doi, ?string $pmcid, array $extra = []): array
    {
        return [
            'source' => 'europe_pmc',
            'doi' => $record['doi'] ?? $doi,
            'pmcid' => $record['pmcid'] ?? $pmcid,
            'europe_pmc_id' => $record['id'] ?? null,
            'europe_pmc_source' => $record['source'] ?? null,
            'license' => $record['license'] ?? null,
            'has_pdf' => $record['hasPDF'] ?? null,
            'is_open_access' => $record['isOpenAccess'] ?? null,
            'full_text_id_list' => $record['fullTextIdList'] ?? null,
            ...$extra,
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

