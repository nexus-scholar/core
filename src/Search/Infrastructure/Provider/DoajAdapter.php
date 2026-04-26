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
 * Adapter for the DOAJ (Directory of Open Access Journals) API.
 *
 * Endpoint: https://doaj.org/api/v1/search/articles/{search_query}
 * Response: JSON with "results" array; each result has "bibjson" sub-object.
 * Pagination: page + pageSize (max 100 per page).
 * Year filtering: via Lucene range syntax appended to query string.
 *
 * Learned from old package:
 *  - Search text is URL-encoded and appended to the path (not as a query param).
 *  - DOI is found in bibjson.identifier[] with type "doi".
 *  - Author names may be in "Family, Given" or "Given Family" format.
 */
final class DoajAdapter extends BaseProviderAdapter
{
    public function alias(): string
    {
        return 'doaj';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return $ns === WorkIdNamespace::DOI || $ns === WorkIdNamespace::DOAJ;
    }

    public function search(SearchQuery $query): array
    {
        $searchText = $query->term->value;

        // Year range via Lucene syntax
        if ($query->yearRange !== null) {
            $from = $query->yearRange->from ?? 1000;
            $to   = $query->yearRange->to   ?? 3000;
            $searchText = "({$searchText}) AND bibjson.year:[{$from} TO {$to}]";
        }

        $url = "{$this->config->baseUrl}/v1/search/articles/" . urlencode($searchText);

        $params = $this->paginationParams($query);

        $response = $this->request($url, $params);

        if (! $response->ok()) {
            return [];
        }

        $items = $this->extractItems($response->body);

        return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        if ($id->namespace !== WorkIdNamespace::DOI) {
            return null;
        }

        // DOAJ doesn't have a direct DOI lookup — search for it
        $url = "{$this->config->baseUrl}/v1/search/articles/" . urlencode("bibjson.doi:\"{$id->value}\"");

        $response = $this->request($url, ['page' => 1, 'pageSize' => 1]);

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
        $bibjson = $raw['bibjson'] ?? [];
        $extractor = new FieldExtractor($bibjson);

        $ids = WorkIdSet::empty();

        // Extract DOI from bibjson.identifier[]
        $identifiers = $extractor->getList('identifier');

        foreach ($identifiers as $ident) {
            $type = $ident['type'] ?? null;
            $val  = $ident['id']   ?? null;

            if ($type === 'doi' && is_string($val) && $val !== '') {
                $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $val));
                break;
            }
        }

        // Use DOAJ record id if available
        if (! empty($raw['id'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOAJ, $raw['id']));
        }

        $title = $extractor->getString('title');

        if ($title === '') {
            $title = 'Unknown Title';
        }

        $yearVal = $extractor->get('year');
        $year    = ($yearVal !== null && is_numeric($yearVal)) ? (int) $yearVal : null;

        $abstract = $extractor->getString('abstract');
        $abstract = $abstract !== '' ? $abstract : null;

        // Authors — DOAJ returns bibjson.author[] with "name" field
        $authors = [];

        foreach ($extractor->getList('author') as $au) {
            $name = $au['name'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                continue;
            }

            $parsed    = $this->parseAuthorName(trim($name));
            $authors[] = new Author(familyName: $parsed['family'], givenName: $parsed['given']);
        }

        // Venue from journal title
        $venue     = null;
        $venueName = $extractor->getString('journal.title');

        if ($venueName !== '') {
            $issn = null;

            foreach ($identifiers as $ident) {
                if (($ident['type'] ?? '') === 'eissn' || ($ident['type'] ?? '') === 'pissn') {
                    $issn = $ident['id'] ?? null;
                    break;
                }
            }

            $venue = new Venue(name: $venueName, issn: $issn, type: 'journal');
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
            rawData:        $rawData,
        );
    }

    protected function paginationParams(SearchQuery $query): array
    {
        // DOAJ uses 1-indexed pages, pageSize max 100
        $pageSize = min($query->maxResults, 100);
        $page     = (int) floor($query->offset / max($pageSize, 1)) + 1;

        return [
            'page'     => $page,
            'pageSize' => $pageSize,
        ];
    }

    protected function extractItems(array $body): array
    {
        return $body['results'] ?? [];
    }
}
