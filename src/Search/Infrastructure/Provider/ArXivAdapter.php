<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use GuzzleHttp\Promise\PromiseInterface;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

/**
 * Adapter for the arXiv Atom XML API.
 *
 * Notes:
 *  - arXiv supports only the ARXIV namespace (no DOI from API directly).
 *  - Response is Atom XML — parsed with SimpleXML.
 *  - Rate: 3 req/sec.
 *  - Does NOT support snowballing (no citation data).
 */
final class ArXivAdapter extends BaseProviderAdapter
{
    public function alias(): string
    {
        return 'arxiv';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return $ns === WorkIdNamespace::ARXIV;
    }

    public function search(SearchQuery $query): array
    {
        $params = array_merge(
            ['search_query' => $this->searchQuery($query)],
            $this->paginationParams($query),
        );

        $response = $this->request('http://export.arxiv.org/api/query', $params);

        if (! $response->ok()) {
            return [];
        }

        $entries = $this->parseAtomXml($response->rawBody);

        return $this->filterByYearRange(
            array_map(fn (array $entry) => $this->normalize($entry, $query), $entries),
            $query,
        );
    }

    public function searchAsync(SearchQuery $query): PromiseInterface
    {
        $params = array_merge(
            ['search_query' => $this->searchQuery($query)],
            $this->paginationParams($query),
        );

        return $this->requestAsync('http://export.arxiv.org/api/query', $params)
            ->then(function (HttpResponse $response) use ($query) {
                if (! $response->ok()) {
                    return [];
                }

                $entries = $this->parseAtomXml($response->rawBody);

                return $this->filterByYearRange(
                    array_map(fn (array $entry) => $this->normalize($entry, $query), $entries),
                    $query,
                );
            });
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        if ($id->namespace !== WorkIdNamespace::ARXIV) {
            return null;
        }

        $response = $this->request('http://export.arxiv.org/api/query', [
            'id_list' => $id->value,
        ]);

        if (! $response->ok()) {
            return null;
        }

        $entries = $this->parseAtomXml($response->rawBody);

        if ($entries === []) {
            return null;
        }

        return $this->normalize($entries[0], new SearchQuery(
            term: new SearchTerm('fetch'),
        ));
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        $ids = WorkIdSet::empty();

        if (! empty($raw['id'])) {
            // Extract arXiv ID from URL: http://arxiv.org/abs/2301.12345v1
            if (preg_match('/abs\/([^\sv]+)/', $raw['id'], $m)) {
                $arxivId = preg_replace('/v\d+$/', '', $m[1]);
                $ids = $ids->add(new WorkId(WorkIdNamespace::ARXIV, $arxivId));
            }
        }

        $title = trim(preg_replace('/\s+/', ' ', $raw['title'] ?? 'Unknown Title'));
        $abstract = trim($raw['summary'] ?? '');
        $abstract = $abstract !== '' ? $abstract : null;

        $year = null;

        if (! empty($raw['published'])) {
            if (preg_match('/^(\d{4})/', $raw['published'], $m)) {
                $year = (int) $m[1];
            }
        }

        $authors = [];

        foreach ($raw['authors'] ?? [] as $authorName) {
            $parts = explode(', ', $authorName, 2);
            $family = count($parts) === 2 ? trim($parts[0]) : trim($authorName);
            $given = count($parts) === 2 ? trim($parts[1]) : null;

            $authors[] = new Author(familyName: $family, givenName: $given);
        }

        $venue = new Venue(name: 'arXiv', type: 'repository');

        $rawData = $query->includeRawData ? $raw : null;

        return ScholarlyWork::reconstitute(
            ids: $ids,
            title: $title,
            sourceProvider: $this->alias(),
            year: $year,
            authors: AuthorList::fromArray($authors),
            venue: $venue,
            abstract: $abstract,
            rawData: $rawData,
        );
    }

    protected function paginationParams(SearchQuery $query): array
    {
        return [
            'start' => $query->offset,
            'max_results' => $query->maxResults,
        ];
    }

    private function searchQuery(SearchQuery $query): string
    {
        $searchQuery = "all:{$query->term->value}";

        if ($query->yearRange === null || $query->yearRange->isUnbounded()) {
            return $searchQuery;
        }

        $from = $query->yearRange->from ?? 1000;
        $to = $query->yearRange->to ?? 9999;

        return sprintf(
            '(%s) AND submittedDate:[%04d01010000 TO %04d12312359]',
            $searchQuery,
            $from,
            $to,
        );
    }

    /**
     * @param  ScholarlyWork[]  $works
     * @return ScholarlyWork[]
     */
    private function filterByYearRange(array $works, SearchQuery $query): array
    {
        if ($query->yearRange === null || $query->yearRange->isUnbounded()) {
            return $works;
        }

        return array_values(array_filter(
            $works,
            fn (ScholarlyWork $work): bool => $work->year() !== null
                && $query->yearRange->contains($work->year()),
        ));
    }

    protected function extractItems(array $body): array
    {
        // Not used directly — arXiv returns XML, so parseAtomXml() is used.
        return [];
    }

    /**
     * Parse Atom XML feed into an array of entry arrays.
     *
     * @return array<int, array{id: string, title: string, summary: string, published: string, authors: string[]}>
     */
    private function parseAtomXml(string $xml): array
    {
        if (trim($xml) === '') {
            return [];
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);

        if ($feed === false) {
            return [];
        }

        $entries = [];

        foreach ($feed->entry as $entry) {
            $authors = [];

            foreach ($entry->author as $author) {
                $authors[] = (string) $author->name;
            }

            $categories = [];

            foreach ($entry->category as $cat) {
                $term = (string) $cat['term'];

                if ($term !== '') {
                    $categories[] = $term;
                }
            }

            $entries[] = [
                'id' => (string) $entry->id,
                'title' => (string) $entry->title,
                'summary' => (string) $entry->summary,
                'published' => (string) $entry->published,
                'authors' => $authors,
                'categories' => $categories,
            ];
        }

        return $entries;
    }
}
