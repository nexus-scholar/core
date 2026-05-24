<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\CitationNetwork\Domain\Port\SnowballingProviderPort;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

final class CrossrefAdapter extends BaseProviderAdapter implements SnowballingProviderPort
{
    public function alias(): string
    {
        return 'crossref';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return in_array($ns, [WorkIdNamespace::DOI, WorkIdNamespace::PUBMED], true);
    }

    public function search(SearchQuery $query): array
    {
        $params = array_merge(
            [
                'query' => $query->term->value,
                'mailto' => $this->config->mailTo ?? '',
            ],
            $this->paginationParams($query),
        );

        if ($query->yearRange !== null) {
            $filters = [];

            if ($query->yearRange->from !== null) {
                $filters[] = "from-pub-date:{$query->yearRange->from}";
            }

            if ($query->yearRange->to !== null) {
                $filters[] = "until-pub-date:{$query->yearRange->to}";
            }

            if ($filters !== []) {
                $params['filter'] = implode(',', $filters);
            }
        }

        $response = $this->request("{$this->config->baseUrl}/works", $params);

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

        $response = $this->request(
            "{$this->config->baseUrl}/works/{$id->value}",
            ['mailto' => $this->config->mailTo ?? '']
        );

        if (! $response->ok()) {
            return null;
        }

        $item = $response->body['message'] ?? [];

        if ($item === []) {
            return null;
        }

        return $this->normalize($item, new SearchQuery(
            term: new SearchTerm('fetch'),
        ));
    }

    public function supportsSnowballing(ScholarlyWork $seed, SnowballDirection $direction): bool
    {
        return $direction === SnowballDirection::BACKWARD
            && $this->doiForWork($seed) !== null;
    }

    /**
     * Crossref's public work metadata exposes deposited references. Citation
     * lists require Crossref Cited-by participation and are intentionally not
     * modelled as public forward snowballing here.
     *
     * @return list<ScholarlyWork>
     */
    public function fetchCitingWorks(ScholarlyWork $seed, int $limit): array
    {
        return [];
    }

    /**
     * @return list<ScholarlyWork>
     */
    public function fetchReferencedWorks(ScholarlyWork $seed, int $limit): array
    {
        $doi = $this->doiForWork($seed);

        if ($doi === null || $limit < 1) {
            return [];
        }

        $response = $this->request(
            "{$this->config->baseUrl}/works/{$doi}",
            ['mailto' => $this->config->mailTo ?? ''],
        );

        if (! $response->ok()) {
            return [];
        }

        $references = $response->body['message']['reference'] ?? [];

        if (! is_array($references)) {
            return [];
        }

        $works = [];

        foreach ($references as $reference) {
            if (count($works) >= $limit) {
                break;
            }

            if (! is_array($reference)) {
                continue;
            }

            $work = $this->normalizeReference($reference);

            if ($work !== null) {
                $works[] = $work;
            }
        }

        return $works;
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        $ids = WorkIdSet::empty();

        if (! empty($raw['DOI'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $raw['DOI']));
        }

        // Crossref title is an array — use first element
        $titleRaw = $raw['title'][0] ?? null;
        $title = ($titleRaw !== null && trim($titleRaw) !== '') ? $titleRaw : 'Unknown Title';

        // Year from date-parts
        $year = null;

        if (! empty($raw['published']['date-parts'][0][0])) {
            $year = (int) $raw['published']['date-parts'][0][0];
        } elseif (! empty($raw['published-print']['date-parts'][0][0])) {
            $year = (int) $raw['published-print']['date-parts'][0][0];
        }

        $cited = $this->extractInt($raw, 'is-referenced-by-count');

        // Authors
        $authors = [];

        foreach ($this->extractArray($raw, 'author') as $authorRaw) {
            $family = $authorRaw['family'] ?? null;

            if ($family === null) {
                continue;
            }

            $given = $authorRaw['given'] ?? null;
            $orcid = null;
            $orcidRaw = $authorRaw['ORCID'] ?? null;

            if ($orcidRaw !== null) {
                $orcidValue = preg_replace('/^https?:\/\/orcid\.org\//', '', $orcidRaw);

                try {
                    $orcid = new OrcidId($orcidValue);
                } catch (\InvalidArgumentException) {
                    // ignore malformed ORCID
                }
            }

            $authors[] = new Author(familyName: $family, givenName: $given, orcid: $orcid);
        }

        // Venue
        $venue = null;
        $venueName = $raw['container-title'][0] ?? null;

        if ($venueName !== null && trim($venueName) !== '') {
            $issn = $raw['ISSN'][0] ?? null;
            $typeRaw = $raw['type'] ?? null;
            $typeMap = [
                'journal-article' => 'journal',
                'proceedings-article' => 'conference',
                'book-chapter' => 'book',
                'posted-content' => 'repository',
            ];
            $type = $typeMap[$typeRaw] ?? null;

            $venue = new Venue(name: $venueName, issn: $issn, type: $type);
        }

        $rawData = $query->includeRawData ? $raw : null;

        return ScholarlyWork::reconstitute(
            ids: $ids,
            title: $title,
            sourceProvider: $this->alias(),
            year: $year,
            authors: AuthorList::fromArray($authors),
            venue: $venue,
            citedByCount: $cited,
            rawData: $rawData,
        );
    }

    protected function paginationParams(SearchQuery $query): array
    {
        return [
            'rows' => $query->maxResults,
            'offset' => $query->offset,
        ];
    }

    protected function extractItems(array $body): array
    {
        return $body['message']['items'] ?? [];
    }

    private function doiForWork(ScholarlyWork $work): ?string
    {
        return $work->ids()->findByNamespace(WorkIdNamespace::DOI)?->value;
    }

    private function normalizeReference(array $raw): ?ScholarlyWork
    {
        $doi = $this->extractString($raw, 'DOI', 'doi');
        $title = $this->extractString($raw, 'article-title', 'series-title', 'volume-title');
        $unstructured = $this->extractString($raw, 'unstructured');

        if ($title === null) {
            $title = $unstructured;
        }

        if ($title === null && $doi !== null) {
            $title = "Crossref reference {$doi}";
        }

        if ($title === null) {
            return null;
        }

        $ids = WorkIdSet::empty();

        if ($doi !== null) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $doi));
        }

        $venue = null;
        $venueName = $this->extractString($raw, 'journal-title');

        if ($venueName !== null) {
            $venue = new Venue(name: $venueName);
        }

        $authors = [];
        $author = $this->extractString($raw, 'author');

        if ($author !== null) {
            $name = $this->parseAuthorName($author);
            $authors[] = new Author(
                familyName: $name['family'],
                givenName: $name['given'],
            );
        }

        return ScholarlyWork::reconstitute(
            ids: WorkIdSet::fromArray($ids->all()),
            title: $title,
            sourceProvider: $this->alias(),
            year: $this->extractInt($raw, 'year'),
            authors: AuthorList::fromArray($authors),
            venue: $venue,
            rawData: null,
        );
    }
}
