<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Closure;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
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

        $esearchResult = $this->parseEsearchResponse($esearchResponse->rawBody);

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

            $articles = $this->parseEfetchResponse($efetchResponse->rawBody, $query);

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

                $esearchResult = $this->parseEsearchResponse($esearchResponse->rawBody);

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

                        $articles = $this->parseEfetchResponse($efetchResponse->rawBody, $query);

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
        $results = $this->parseEfetchResponse($response->rawBody, $query);

        return $results[0] ?? null;
    }

    private function parseEsearchResponse(string $xml): ?array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);

        if ($root === false) {
            return null;
        }

        $count    = (int) ((string) ($root->Count ?? '0'));
        $webenv   = (string) ($root->WebEnv ?? '');
        $queryKey = (string) ($root->QueryKey ?? '');

        $ids = [];
        if (isset($root->IdList)) {
            foreach ($root->IdList->Id as $idElem) {
                $ids[] = (string) $idElem;
            }
        }

        return [
            'count'    => $count,
            'ids'      => $ids,
            'webenv'   => $webenv,
            'queryKey' => $queryKey,
        ];
    }

    private function parseEfetchResponse(string $xml, SearchQuery $query): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);

        if ($root === false) {
            return [];
        }

        $works = [];
        foreach ($root->PubmedArticle as $articleNode) {
            $work = $this->normalizeXmlArticle($articleNode, $query);
            if ($work !== null) {
                $works[] = $work;
            }
        }

        return $works;
    }

    private function normalizeXmlArticle(\SimpleXMLElement $node, SearchQuery $query): ?ScholarlyWork
    {
        $medlineCitation = $node->MedlineCitation ?? null;
        $article         = $medlineCitation?->Article ?? null;

        if ($article === null) {
            return null;
        }

        $title = (string) ($article->ArticleTitle ?? '');
        if (trim($title) === '') {
            return null;
        }

        $ids  = WorkIdSet::empty();
        $pmid = (string) ($medlineCitation->PMID ?? '');
        if ($pmid !== '') {
            $ids = $ids->add(new WorkId(WorkIdNamespace::PUBMED, $pmid));
        }

        $doi = $this->extractDoiFromArticle($article, $node);
        if ($doi !== null) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $doi));
        }

        $abstract = $this->extractAbstract($article);
        $authors  = $this->extractAuthors($article);
        $year     = $this->extractYear($article);

        $venue     = null;
        $venueName = (string) ($article->Journal?->Title ?? '');
        if ($venueName !== '') {
            $issn = (string) ($article->Journal?->ISSN ?? '');
            $venue = new Venue(
                name: $venueName,
                issn: $issn !== '' ? $issn : null,
                type: 'journal',
            );
        }

        return ScholarlyWork::reconstitute(
            ids:            $ids,
            title:          $title,
            sourceProvider: $this->alias(),
            year:           $year,
            authors:        AuthorList::fromArray($authors),
            venue:          $venue,
            abstract:       $abstract,
            rawData:        $query->includeRawData ? $this->xmlNodeToArray($node) : null,
        );
    }

    private function extractDoiFromArticle(\SimpleXMLElement $article, \SimpleXMLElement $node): ?string
    {
        foreach ($article->ELocationID ?? [] as $eloc) {
            if ((string) ($eloc['EIdType'] ?? '') === 'doi') {
                $doiText = (string) $eloc;
                if ($doiText !== '') return $doiText;
            }
        }

        $articleIds = $node->PubmedData?->ArticleIdList ?? null;
        if ($articleIds !== null) {
            foreach ($articleIds->ArticleId as $aid) {
                if ((string) ($aid['IdType'] ?? '') === 'doi') {
                    $doiText = (string) $aid;
                    if ($doiText !== '') return $doiText;
                }
            }
        }

        return null;
    }

    private function extractAbstract(\SimpleXMLElement $article): ?string
    {
        $abstractElem = $article->Abstract ?? null;
        if ($abstractElem === null) return null;
        $parts = [];
        foreach ($abstractElem->AbstractText ?? [] as $text) {
            $content = (string) $text;
            if ($content !== '') $parts[] = $content;
        }
        $full = implode(' ', $parts);
        return $full !== '' ? $full : null;
    }

    private function extractAuthors(\SimpleXMLElement $article): array
    {
        $authorList = $article->AuthorList ?? null;
        if ($authorList === null) return [];
        $authors = [];
        foreach ($authorList->Author as $au) {
            $last = (string) ($au->LastName ?? '');
            $fore = (string) ($au->ForeName ?? '');
            if ($last === '') continue;
            $orcid = null;
            foreach ($au->Identifier ?? [] as $idNode) {
                $source = (string) ($idNode['Source'] ?? '');
                if ($source === 'ORCID') {
                    $orcidText = (string) $idNode;
                    if ($orcidText !== '') {
                        if (str_contains($orcidText, 'orcid.org/')) {
                            $orcidText = explode('orcid.org/', $orcidText)[1] ?? '';
                        }
                        if ($orcidText !== '') {
                            try { $orcid = new OrcidId($orcidText); } catch (\InvalidArgumentException) {}
                        }
                    }
                }
            }
            $authors[] = new Author(familyName: $last, givenName: $fore !== '' ? $fore : null, orcid: $orcid);
        }
        return $authors;
    }

    private function extractYear(\SimpleXMLElement $article): ?int
    {
        $pubDate = $article->Journal?->JournalIssue?->PubDate ?? null;
        if ($pubDate === null) return null;
        $yearText = (string) ($pubDate->Year ?? '');
        if ($yearText !== '') return (int) $yearText;
        $medlineDate = (string) ($pubDate->MedlineDate ?? '');
        if ($medlineDate !== '' && preg_match('/\d{4}/', $medlineDate, $matches)) {
            return (int) $matches[0];
        }
        return null;
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

    private function xmlNodeToArray(\SimpleXMLElement $node): array
    {
        $json = json_encode($node);
        if ($json === false) return [];
        $result = json_decode($json, true);
        return is_array($result) ? $result : [];
    }
}
