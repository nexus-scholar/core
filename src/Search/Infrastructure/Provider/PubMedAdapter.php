<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Closure;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Psr\Log\LoggerInterface;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\RateLimiterPort;

/**
 * Adapter for NCBI PubMed E-utilities.
 *
 * Uses a two-step pipeline:
 *   1. esearch.fcgi → get PMIDs + WebEnv/QueryKey for history server
 *   2. efetch.fcgi  → fetch full article metadata in XML
 */
final class PubMedAdapter extends BaseProviderAdapter
{
    private PubMedXmlParser $parser;

    public function __construct(
        HttpClientPort    $http,
        RateLimiterPort   $rateLimiter,
        ProviderConfig    $config,
        ?LoggerInterface  $logger = null,
        ?Closure          $sleeper = null,
        ?PubMedXmlParser  $parser = null,
    ) {
        parent::__construct($http, $rateLimiter, $config, $logger, $sleeper);
        $this->parser = $parser ?? new PubMedXmlParser();
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
        $esearchParams = [
            'db'         => 'pubmed',
            'term'       => $this->buildSearchTerm($query),
            'retmode'    => 'xml',
            'retmax'     => min($query->maxResults, 10000),
            'usehistory' => 'y',
        ];

        if ($this->config->apiKey !== null) {
            $esearchParams['api_key'] = $this->config->apiKey;
        }

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

        // Step 2: efetch — retrieve full article metadata in batches
        $batchSize = 200;
        $collected = [];

        for ($start = 0; $start < min($esearchResult['count'], $query->maxResults); $start += $batchSize) {
            $efetchParams = [
                'db'        => 'pubmed',
                'retmode'   => 'xml',
                'retstart'  => $start,
                'retmax'    => $batchSize,
            ];

            if ($esearchResult['webenv'] !== '' && $esearchResult['queryKey'] !== '') {
                $efetchParams['query_key'] = $esearchResult['queryKey'];
                $efetchParams['WebEnv']    = $esearchResult['webenv'];
            } else {
                $batch = array_slice($esearchResult['ids'], $start, $batchSize);
                if ($batch === []) break;
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

    public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface
    {
        $esearchParams = [
            'db'         => 'pubmed',
            'term'       => $this->buildSearchTerm($query),
            'retmode'    => 'xml',
            'retmax'     => min($query->maxResults, 10000),
            'usehistory' => 'y',
        ];

        if ($this->config->apiKey !== null) {
            $esearchParams['api_key'] = $this->config->apiKey;
        }

        return $this->requestAsync("{$this->config->baseUrl}/esearch.fcgi", $esearchParams)
            ->then(function ($esearchResponse) use ($query) {
                if (! $esearchResponse->ok() || $esearchResponse->rawBody === '') {
                    return [];
                }

                $esearchResult = $this->parser->parseEsearchResponse($esearchResponse->rawBody);

                if ($esearchResult === null || $esearchResult['count'] === 0) {
                    return [];
                }

                $batchSize = min($esearchResult['count'], $query->maxResults, 200);
                $efetchParams = [
                    'db'        => 'pubmed',
                    'retmode'   => 'xml',
                    'retstart'  => 0,
                    'retmax'    => $batchSize,
                    'query_key' => $esearchResult['queryKey'],
                    'WebEnv'    => $esearchResult['webenv'],
                ];

                if ($this->config->apiKey !== null) {
                    $efetchParams['api_key'] = $this->config->apiKey;
                }

                return $this->requestAsync("{$this->config->baseUrl}/efetch.fcgi", $efetchParams)
                    ->then(function ($efetchResponse) use ($query) {
                        if (! $efetchResponse->ok() || $efetchResponse->rawBody === '') {
                            return [];
                        }

                        $articles = $this->parser->parseEfetchResponse($efetchResponse->rawBody, $query);

                        return array_slice($articles, 0, $query->maxResults);
                    });
            });
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        $identifier = match ($id->namespace) {
            WorkIdNamespace::PUBMED => $id->value,
            WorkIdNamespace::DOI   => null,
            default                => null,
        };

        if ($identifier === null) {
            return null;
        }

        $params = [
            'db'      => 'pubmed',
            'id'      => $identifier,
            'retmode' => 'xml',
        ];

        if ($this->config->apiKey !== null) {
            $params['api_key'] = $this->config->apiKey;
        }

        $response = $this->request("{$this->config->baseUrl}/efetch.fcgi", $params);

        if (! $response->ok() || $response->rawBody === '') {
            return null;
        }

        $query   = new SearchQuery(term: new \Nexus\Search\Domain\SearchTerm('fetch'));
        $results = $this->parser->parseEfetchResponse($response->rawBody, $query);

        return $results[0] ?? null;
    }

    private function buildSearchTerm(SearchQuery $query): string
    {
        $term = $query->term->value;
        if ($query->yearRange !== null) {
            $from = $query->yearRange->from ?? 1000;
            $to   = $query->yearRange->to   ?? 3000;
            $term = "({$term}) AND {$from}:{$to}[Date - Publication]";
        }
        return $term;
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
