<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Closure;
use Nexus\Search\Application\ProviderExecution\CallbackProviderSearchTask;
use Nexus\Search\Application\ProviderExecution\ConcurrentSearchProviderPort;
use Nexus\Search\Application\ProviderExecution\ProviderSearchTask;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Psr\Log\LoggerInterface;

/**
 * Adapter for NCBI PubMed E-utilities.
 *
 * Uses a two-step pipeline:
 *   1. esearch.fcgi → get PMIDs + WebEnv/QueryKey for history server
 *   2. efetch.fcgi  → fetch full article metadata in XML
 */
final class PubMedAdapter extends BaseProviderAdapter implements ConcurrentSearchProviderPort
{
    private PubMedXmlParser $parser;

    public function __construct(
        HttpClientPort $http,
        RateLimiterPort $rateLimiter,
        ProviderConfig $config,
        ?LoggerInterface $logger = null,
        ?Closure $sleeper = null,
        ?PubMedXmlParser $parser = null,
    ) {
        parent::__construct($http, $rateLimiter, $config, $logger, $sleeper);
        $this->parser = $parser ?? new PubMedXmlParser;
    }

    public function alias(): string
    {
        return 'pubmed';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return $ns === WorkIdNamespace::DOI || $ns === WorkIdNamespace::PUBMED;
    }

    public function search(SearchQuery $query): array
    {
        // Step 1: esearch — get PMIDs and history server params
        $esearchParams = $this->esearchParams($query);

        $esearchResponse = $this->request(
            "{$this->config->baseUrl}/esearch.fcgi",
            $esearchParams
        );

        if (! $esearchResponse->ok() || $esearchResponse->rawBody === '') {
            return [];
        }

        $esearchResult = $this->parser->parseEsearchResponse($esearchResponse->rawBody);

        if ($esearchResult === null || $esearchResult['count'] === 0) {
            return [];
        }

        return $this->fetchArticles($esearchResult, $query);
    }

    public function beginSearch(SearchQuery $query): ?ProviderSearchTask
    {
        $url = "{$this->config->baseUrl}/esearch.fcgi";
        $params = $this->esearchParams($query);
        $pending = $this->beginRequest($url, $params);

        if ($pending === null) {
            return null;
        }

        return new CallbackProviderSearchTask($this->alias(), function () use ($pending, $url, $params, $query): array {
            $esearchResponse = $this->waitForPendingRequest($pending, $url, $params);

            if (! $esearchResponse->ok() || $esearchResponse->rawBody === '') {
                return [];
            }

            $esearchResult = $this->parser->parseEsearchResponse($esearchResponse->rawBody);

            if ($esearchResult === null || $esearchResult['count'] === 0) {
                return [];
            }

            return $this->fetchArticles($esearchResult, $query);
        });
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        $identifier = match ($id->namespace) {
            WorkIdNamespace::PUBMED => $id->value,
            WorkIdNamespace::DOI => null,
            default => null,
        };

        if ($identifier === null) {
            return null;
        }

        $params = [
            'db' => 'pubmed',
            'id' => $identifier,
            'retmode' => 'xml',
        ];

        if ($this->config->apiKey !== null) {
            $params['api_key'] = $this->config->apiKey;
        }

        $response = $this->request("{$this->config->baseUrl}/efetch.fcgi", $params);

        if (! $response->ok() || $response->rawBody === '') {
            return null;
        }

        $query = new SearchQuery(term: new SearchTerm('fetch'));
        $results = $this->parser->parseEfetchResponse($response->rawBody, $query);

        return $results[0] ?? null;
    }

    private function buildSearchTerm(SearchQuery $query): string
    {
        $term = $query->term->value;
        if ($query->yearRange !== null) {
            $from = $query->yearRange->from ?? 1000;
            $to = $query->yearRange->to ?? 3000;
            $term = "({$term}) AND {$from}:{$to}[Date - Publication]";
        }

        return $term;
    }

    private function esearchParams(SearchQuery $query): array
    {
        $params = [
            'db' => 'pubmed',
            'term' => $this->buildSearchTerm($query),
            'retmode' => 'xml',
            'retmax' => min($query->maxResults, 10000),
            'usehistory' => 'y',
        ];

        if ($this->config->apiKey !== null) {
            $params['api_key'] = $this->config->apiKey;
        }

        return $params;
    }

    /**
     * @param  array{count: int, ids: list<string>, webenv: string, queryKey: string}  $esearchResult
     * @return list<ScholarlyWork>
     */
    private function fetchArticles(array $esearchResult, SearchQuery $query): array
    {
        $batchSize = 200;
        $collected = [];

        for ($start = 0; $start < min($esearchResult['count'], $query->maxResults); $start += $batchSize) {
            $efetchParams = [
                'db' => 'pubmed',
                'retmode' => 'xml',
                'retstart' => $start,
                'retmax' => $batchSize,
            ];

            if ($esearchResult['webenv'] !== '' && $esearchResult['queryKey'] !== '') {
                $efetchParams['query_key'] = $esearchResult['queryKey'];
                $efetchParams['WebEnv'] = $esearchResult['webenv'];
            } else {
                $batch = array_slice($esearchResult['ids'], $start, $batchSize);
                if ($batch === []) {
                    break;
                }
                $efetchParams['id'] = implode(',', $batch);
            }

            if ($this->config->apiKey !== null) {
                $efetchParams['api_key'] = $this->config->apiKey;
            }

            $efetchResponse = $this->request(
                "{$this->config->baseUrl}/efetch.fcgi",
                $efetchParams
            );

            if (! $efetchResponse->ok() || $efetchResponse->rawBody === '') {
                continue;
            }

            $articles = $this->parser->parseEfetchResponse($efetchResponse->rawBody, $query);

            foreach ($articles as $work) {
                if (count($collected) >= $query->maxResults) {
                    break 2;
                }

                $collected[] = $work;
            }
        }

        return $collected;
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        throw new \LogicException('PubMedAdapter::normalize() must never be called.');
    }

    protected function paginationParams(SearchQuery $query): array
    {
        return [];
    }

    protected function extractItems(array $body): array
    {
        return [];
    }
}
