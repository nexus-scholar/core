<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

/**
 * Adapter for Semantic Scholar Academic Graph API.
 *
 * Uses the /paper/search/bulk endpoint (cursor-based pagination, up to 1000/page)
 * for searches, and /paper/{id} for fetchById.  The bulk endpoint supports
 * boolean operators via +, |, and - instead of AND/OR/NOT.
 *
 * Learned from old package: S2 bulk endpoint requires translating boolean
 * operators and uses a continuation "token" instead of offset pagination.
 */
final class SemanticScholarAdapter extends BaseProviderAdapter
{
    private const FIELDS = 'paperId,externalIds,title,abstract,year,venue,authors,citationCount,isOpenAccess';

    public function alias(): string
    {
        return 'semantic_scholar';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return in_array($ns, [
            WorkIdNamespace::DOI,
            WorkIdNamespace::S2,
            WorkIdNamespace::ARXIV,
            WorkIdNamespace::PUBMED,
        ], true);
    }

    public function search(SearchQuery $query): array
    {
        $params = [
            'query'  => $this->toBulkQuery($query->term->value),
            'fields' => self::FIELDS,
        ];

        // Year range filter (S2 bulk supports "2020-2024" syntax)
        if ($query->yearRange !== null) {
            $from = $query->yearRange->from;
            $to   = $query->yearRange->to;

            if ($from !== null && $to !== null) {
                $params['year'] = "{$from}-{$to}";
            } elseif ($from !== null) {
                $params['year'] = "{$from}-";
            } elseif ($to !== null) {
                $params['year'] = "-{$to}";
            }
        }

        $headers = [];

        if ($this->config->apiKey !== null) {
            $headers['x-api-key'] = $this->config->apiKey;
        }

        // Bulk endpoint with continuation-token pagination
        $collected = [];
        $token     = null;
        $maxResults = $query->maxResults;

        while (count($collected) < $maxResults) {
            $requestParams = $params;

            if ($token !== null) {
                $requestParams['token'] = $token;
            }

            try {
                $response = $this->request(
                    "{$this->config->baseUrl}/graph/v1/paper/search/bulk",
                    $requestParams,
                    $headers
                );

                if (! $response->ok()) {
                    break;
                }

                $items = $this->extractItems($response->body);

                if ($items === []) {
                    break;
                }

                foreach ($items as $raw) {
                    if (count($collected) >= $maxResults) {
                        break 2;
                    }

                    $collected[] = $this->normalize($raw, $query);
                }

                // Continuation token for next page
                $token = $response->body['token'] ?? null;

                if ($token === null) {
                    break;
                }
            } catch (\Nexus\Search\Domain\Exception\ProviderUnavailable $e) {
                // Return partial results collected so far
                break;
            }
        }

        return $collected;
    }

    /**
     * Single-page async fetch. Supports up to ~1000 results per S2 bulk page.
     * For maxResults > 1000, use the synchronous search() instead.
     */
    public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface
    {
        $params = [
            'query'  => $this->toBulkQuery($query->term->value),
            'fields' => self::FIELDS,
        ];

        if ($query->yearRange !== null) {
            $from = $query->yearRange->from;
            $to   = $query->yearRange->to;

            if ($from !== null && $to !== null) {
                $params['year'] = "{$from}-{$to}";
            } elseif ($from !== null) {
                $params['year'] = "{$from}-";
            } elseif ($to !== null) {
                $params['year'] = "-{$to}";
            }
        }

        $headers = [];

        if ($this->config->apiKey !== null) {
            $headers['x-api-key'] = $this->config->apiKey;
        }

        return $this->requestAsync(
            "{$this->config->baseUrl}/graph/v1/paper/search/bulk",
            $params,
            $headers
        )->then(function (\Nexus\Search\Domain\Port\HttpResponse $response) use ($query) {
            if (! $response->ok()) {
                return [];
            }

            $items = $this->extractItems($response->body);
            $maxResults = $query->maxResults;
            $collected = [];

            foreach ($items as $raw) {
                if (count($collected) >= $maxResults) {
                    break;
                }

                $collected[] = $this->normalize($raw, $query);
            }

            return $collected;
        });
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        $identifier = match ($id->namespace) {
            WorkIdNamespace::DOI    => "DOI:{$id->value}",
            WorkIdNamespace::S2     => $id->value,
            WorkIdNamespace::ARXIV  => "ARXIV:{$id->value}",
            WorkIdNamespace::PUBMED => "PMID:{$id->value}",
            default                 => null,
        };

        if ($identifier === null) {
            return null;
        }

        $headers = [];

        if ($this->config->apiKey !== null) {
            $headers['x-api-key'] = $this->config->apiKey;
        }

        $response = $this->request(
            "{$this->config->baseUrl}/graph/v1/paper/{$identifier}",
            ['fields' => self::FIELDS],
            $headers
        );

        if (! $response->ok()) {
            return null;
        }

        return $this->normalize($response->body, new SearchQuery(
            term: new \Nexus\Search\Domain\SearchTerm('fetch'),
        ));
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        $ids = WorkIdSet::empty();

        if (! empty($raw['paperId'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::S2, $raw['paperId']));
        }

        $externalIds = $raw['externalIds'] ?? [];

        if (! empty($externalIds['DOI'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $externalIds['DOI']));
        }

        if (! empty($externalIds['ArXiv'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::ARXIV, $externalIds['ArXiv']));
        }

        if (! empty($externalIds['PubMed'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::PUBMED, $externalIds['PubMed']));
        }

        $title    = $this->extractString($raw, 'title') ?? 'Unknown Title';
        $year     = $this->extractInt($raw, 'year');
        $cited    = $this->extractInt($raw, 'citationCount');
        $abstract = $this->extractString($raw, 'abstract');

        $venue = null;
        $venueName = $this->extractString($raw, 'venue');

        if ($venueName !== null) {
            $venue = new Venue(name: $venueName);
        }

        // S2 returns "Given Family" name order — split last token as family
        $authors = [];

        foreach ($this->extractArray($raw, 'authors') as $authorRaw) {
            $name = $authorRaw['name'] ?? null;

            if ($name === null) {
                continue;
            }

            $parts = explode(' ', $name);

            if (count($parts) === 1) {
                $family = $parts[0];
                $given  = null;
            } else {
                $family = array_pop($parts);
                $given  = implode(' ', $parts);
            }

            $authors[] = new Author(familyName: $family, givenName: $given);
        }

        $rawData = $query->includeRawData ? $raw : null;

        return ScholarlyWork::reconstitute(
            ids:            $ids,
            title:          $title,
            sourceProvider: $this->alias(),
            year:           $year,
            authors:        AuthorList::fromArray($authors),
            venue:          $venue,
            abstract:       $abstract,
            citedByCount:   $cited,
            rawData:        $rawData,
        );
    }

    protected function paginationParams(SearchQuery $query): array
    {
        // Bulk endpoint uses continuation tokens; not used directly.
        return [];
    }

    protected function extractItems(array $body): array
    {
        return $body['data'] ?? [];
    }

    /**
     * Translate standard boolean query into S2 bulk syntax.
     * AND → +, OR → |, NOT → -
     */
    private function toBulkQuery(string $text): string
    {
        $q = preg_replace('/\bAND\b/i', '+', $text);
        $q = preg_replace('/\bOR\b/i', '|', $q);
        $q = preg_replace('/\bNOT\b\s+/i', '-', $q);

        return trim((string) preg_replace('/\s+/', ' ', $q));
    }
}
