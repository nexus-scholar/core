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
 * Adapter for the IEEE Xplore API.
 *
 * Endpoint: https://ieeexploreapi.ieee.org/api/v1/search/articles
 * Auth: Requires API key (passed as "apikey" query param).
 * Response: JSON with "articles" array.
 * Pagination: start_record + max_records (offset-based, max 200 per page).
 * Year filtering: via start_year / end_year params.
 *
 * Learned from old package:
 *  - Rate limit is 1 req/sec (strict).
 *  - Authors are nested: response.authors.authors[].full_name.
 *  - Article number is the IEEE-specific provider ID.
 *  - DOI is a direct field on each article.
 */
final class IeeeAdapter extends BaseProviderAdapter
{
    public function alias(): string
    {
        return 'ieee';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return $ns === WorkIdNamespace::DOI || $ns === WorkIdNamespace::IEEE;
    }

    public function search(SearchQuery $query): array
    {
        if ($this->config->apiKey === null) {
            return []; // IEEE requires an API key
        }

        $params = array_merge(
            [
                'apikey'      => $this->config->apiKey,
                'querytext'   => $query->term->value,
                'format'      => 'json',
                'max_records' => min($query->maxResults, 200),
                'sort_field'  => 'publication_year',
                'sort_order'  => 'desc',
            ],
            $this->paginationParams($query),
        );

        // Year range
        if ($query->yearRange !== null) {
            if ($query->yearRange->from !== null) {
                $params['start_year'] = $query->yearRange->from;
            }

            if ($query->yearRange->to !== null) {
                $params['end_year'] = $query->yearRange->to;
            }
        }

        $response = $this->request("{$this->config->baseUrl}/search/articles", $params);

        if (! $response->ok()) {
            return [];
        }

        $items = $this->extractItems($response->body);

        return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
    }

    public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface
    {
        if ($this->config->apiKey === null) {
            return new \GuzzleHttp\Promise\FulfilledPromise([]); // IEEE requires an API key
        }

        $params = array_merge(
            [
                'apikey'      => $this->config->apiKey,
                'querytext'   => $query->term->value,
                'format'      => 'json',
                'max_records' => min($query->maxResults, 200),
                'sort_field'  => 'publication_year',
                'sort_order'  => 'desc',
            ],
            $this->paginationParams($query),
        );

        // Year range
        if ($query->yearRange !== null) {
            if ($query->yearRange->from !== null) {
                $params['start_year'] = $query->yearRange->from;
            }

            if ($query->yearRange->to !== null) {
                $params['end_year'] = $query->yearRange->to;
            }
        }

        return $this->requestAsync("{$this->config->baseUrl}/search/articles", $params)
            ->then(function (\Nexus\Search\Domain\Port\HttpResponse $response) use ($query) {
                if (! $response->ok()) {
                    return [];
                }

                $items = $this->extractItems($response->body);

                return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
            });
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        if ($this->config->apiKey === null) {
            return null;
        }

        if ($id->namespace === WorkIdNamespace::DOI) {
            $params = [
                'apikey' => $this->config->apiKey,
                'doi'    => $id->value,
                'format' => 'json',
            ];
        } elseif ($id->namespace === WorkIdNamespace::IEEE) {
            $params = [
                'apikey'         => $this->config->apiKey,
                'article_number' => $id->value,
                'format'         => 'json',
            ];
        } else {
            return null;
        }

        $response = $this->request("{$this->config->baseUrl}/search/articles", $params);

        if (! $response->ok()) {
            return null;
        }

        $items = $this->extractItems($response->body);

        if ($items === []) {
            return null;
        }

        return $this->normalize($items[0], new SearchQuery(
            term: new \Nexus\Search\Domain\SearchTerm('fetch'),
        ));
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        $extractor = new FieldExtractor($raw);
        $ids       = WorkIdSet::empty();

        $doi = $extractor->getString('doi');

        if ($doi !== '') {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $doi));
        }

        $articleNumber = $extractor->getString('article_number');

        if ($articleNumber !== '') {
            $ids = $ids->add(new WorkId(WorkIdNamespace::IEEE, $articleNumber));
        }

        $title = $extractor->getString('title');

        if ($title === '') {
            $title = 'Unknown Title';
        }

        $year     = $extractor->getInt('publication_year');
        $abstract = $extractor->getString('abstract');
        $abstract = $abstract !== '' ? $abstract : null;

        // Authors — IEEE nests: { "authors": { "authors": [ { "full_name": "..." } ] } }
        $authors   = [];
        $authorData = $raw['authors'] ?? [];

        if (is_array($authorData) && isset($authorData['authors'])) {
            foreach ($authorData['authors'] as $au) {
                $fullName = $au['full_name'] ?? null;

                if (! is_string($fullName) || trim($fullName) === '') {
                    continue;
                }

                $parsed    = $this->parseAuthorName(trim($fullName));
                $authors[] = new Author(familyName: $parsed['family'], givenName: $parsed['given']);
            }
        }

        // Venue from publication_title
        $venue     = null;
        $venueName = $extractor->getString('publication_title');

        if ($venueName !== '') {
            $issn = $extractor->getString('issn');
            $venue = new Venue(
                name: $venueName,
                issn: $issn !== '' ? $issn : null,
            );
        }

        $cited = $extractor->getInt('citing_paper_count');

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
        return [
            'start_record' => $query->offset + 1, // IEEE is 1-indexed
        ];
    }

    protected function extractItems(array $body): array
    {
        return $body['articles'] ?? [];
    }
}
