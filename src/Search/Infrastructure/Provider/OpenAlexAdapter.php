<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\CitationNetwork\Domain\Port\SnowballingProviderPort;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

final class OpenAlexAdapter extends BaseProviderAdapter implements SnowballingProviderPort
{
    public function alias(): string
    {
        return 'openalex';
    }

    public function supports(WorkIdNamespace $ns): bool
    {
        return in_array($ns, [
            WorkIdNamespace::DOI,
            WorkIdNamespace::OPENALEX,
            WorkIdNamespace::ARXIV,
            WorkIdNamespace::PUBMED,
        ], true);
    }

    public function search(SearchQuery $query): array
    {
        $params = $this->prepareSearchParams($query);

        $response = $this->request("{$this->config->baseUrl}/works", $params);

        if (! $response->ok()) {
            return [];
        }

        $items = $this->extractItems($response->body);

        return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
    }

    public function searchAsync(SearchQuery $query): \GuzzleHttp\Promise\PromiseInterface
    {
        $params = $this->prepareSearchParams($query);

        return $this->requestAsync("{$this->config->baseUrl}/works", $params)
            ->then(function (\Nexus\Search\Domain\Port\HttpResponse $response) use ($query) {
                if (! $response->ok()) {
                    return [];
                }

                $items = $this->extractItems($response->body);

                return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
            });
    }

    private function prepareSearchParams(SearchQuery $query): array
    {
        $params = array_merge(
            [
                'search' => $query->term->value,
                'mailto' => $this->config->mailTo ?? '',
            ],
            $this->paginationParams($query),
        );

        if ($query->yearRange !== null) {
            $from = $query->yearRange->from;
            $to   = $query->yearRange->to;

            if ($from !== null && $to !== null) {
                $params['filter'] = "publication_year:{$from}-{$to}";
            } elseif ($from !== null) {
                $params['filter'] = "publication_year:{$from}-";
            } elseif ($to !== null) {
                $params['filter'] = "publication_year:-{$to}";
            }
        }

        return $params;
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        $identifier = match ($id->namespace) {
            WorkIdNamespace::DOI      => "https://doi.org/{$id->value}",
            WorkIdNamespace::OPENALEX => $id->value,
            WorkIdNamespace::PUBMED   => "pmid:{$id->value}",
            WorkIdNamespace::ARXIV    => "arxiv:{$id->value}",
            default                   => null,
        };

        if ($identifier === null) {
            return null;
        }

        $response = $this->request(
            "{$this->config->baseUrl}/works/{$identifier}",
            ['mailto' => $this->config->mailTo ?? '']
        );

        if (! $response->ok()) {
            return null;
        }

        return $this->normalize($response->body, new SearchQuery(
            term: new \Nexus\Search\Domain\SearchTerm('fetch'),
        ));
    }

    public function supportsSnowballing(ScholarlyWork $seed, SnowballDirection $direction): bool
    {
        if ($direction === SnowballDirection::BACKWARD && $this->referencedWorkIdsFromRawData($seed) !== []) {
            return true;
        }

        return $this->singleWorkIdentifierFor($seed) !== null;
    }

    /**
     * @return list<ScholarlyWork>
     */
    public function fetchCitingWorks(ScholarlyWork $seed, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $openAlexId = $this->openAlexIdForSnowballing($seed);

        if ($openAlexId === null) {
            return [];
        }

        return $this->fetchFilteredWorks("cites:{$openAlexId}", $limit);
    }

    /**
     * @return list<ScholarlyWork>
     */
    public function fetchReferencedWorks(ScholarlyWork $seed, int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $referenceIds = $this->referencedWorkIdsFromRawData($seed);

        if ($referenceIds === []) {
            $seedRaw = $this->fetchRawSeedWork($seed);
            $referenceIds = is_array($seedRaw) ? $this->referencedWorkIdsFromArray($seedRaw) : [];
        }

        if ($referenceIds === []) {
            return [];
        }

        return $this->fetchWorksByOpenAlexIds($referenceIds, $limit);
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        $ids = WorkIdSet::empty();

        if (! empty($raw['ids']['doi'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $raw['ids']['doi']));
        }

        if (! empty($raw['ids']['openalex'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::OPENALEX, $raw['ids']['openalex']));
        }

        if (! empty($raw['ids']['pmid'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::PUBMED, (string) $raw['ids']['pmid']));
        }

        if (! empty($raw['ids']['arxiv'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::ARXIV, $raw['ids']['arxiv']));
        }

        $title   = $this->extractString($raw, 'display_name', 'title') ?? 'Unknown Title';
        $year    = $this->extractInt($raw, 'publication_year');
        $cited   = $this->extractInt($raw, 'cited_by_count');
        $retracted = (bool) ($raw['is_retracted'] ?? false);

        $abstract = null;

        if (! empty($raw['abstract_inverted_index'])) {
            $abstract = $this->reconstructAbstract($raw['abstract_inverted_index']);
        }

        // Venue
        $venue = null;
        $venueName = $this->extractNestedString($raw, 'primary_location.source.display_name');

        if ($venueName !== null) {
            $venue = new Venue(
                name: $venueName,
                issn: $this->extractNestedString($raw, 'primary_location.source.issn_l'),
            );
        }

        // Authors
        $authors = [];

        foreach ($this->extractArray($raw, 'authorships') as $authorship) {
            $displayName = $authorship['author']['display_name'] ?? null;

            if ($displayName === null) {
                continue;
            }

            $parts  = explode(' ', $displayName, 2);
            $given  = count($parts) === 2 ? $parts[0] : null;
            $family = count($parts) === 2 ? $parts[1] : $parts[0];

            $orcidRaw = $authorship['author']['orcid'] ?? null;
            $orcid    = null;

            if ($orcidRaw !== null) {
                $orcidValue = preg_replace('/^https?:\/\/orcid\.org\//', '', $orcidRaw);

                try {
                    $orcid = new OrcidId($orcidValue);
                } catch (\InvalidArgumentException) {
                    // malformed ORCID — ignore
                }
            }

            $authors[] = new Author(
                familyName: $family,
                givenName:  $given,
                orcid:      $orcid,
            );
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
            isRetracted:    $retracted,
            rawData:        $rawData,
        );
    }

    protected function paginationParams(SearchQuery $query): array
    {
        $perPage = min($query->maxResults, 200);
        $page    = (int) floor($query->offset / $perPage) + 1;

        return [
            'per-page' => $perPage,
            'page'     => $page,
        ];
    }

    protected function extractItems(array $body): array
    {
        return $body['results'] ?? [];
    }

    /**
     * Reconstruct abstract string from OpenAlex inverted index format.
     * Format: { "word": [position1, position2, ...], ... }
     */
    private function reconstructAbstract(array $invertedIndex): string
    {
        $positionMap = [];

        foreach ($invertedIndex as $word => $positions) {
            foreach ($positions as $pos) {
                $positionMap[$pos] = $word;
            }
        }

        ksort($positionMap);

        return implode(' ', $positionMap);
    }

    private function singleWorkIdentifierFor(ScholarlyWork $work): ?string
    {
        $ids = $work->ids();

        foreach ([
            WorkIdNamespace::OPENALEX,
            WorkIdNamespace::DOI,
            WorkIdNamespace::PUBMED,
            WorkIdNamespace::ARXIV,
        ] as $namespace) {
            $id = $ids->findByNamespace($namespace);

            if ($id === null) {
                continue;
            }

            return match ($namespace) {
                WorkIdNamespace::OPENALEX => $this->openAlexWorkKey($id->value),
                WorkIdNamespace::DOI => "https://doi.org/{$id->value}",
                WorkIdNamespace::PUBMED => "pmid:{$id->value}",
                WorkIdNamespace::ARXIV => "arxiv:{$id->value}",
                default => null,
            };
        }

        return null;
    }

    private function openAlexIdForSnowballing(ScholarlyWork $work): ?string
    {
        $rawId = $work->rawData()['id'] ?? $work->rawData()['ids']['openalex'] ?? null;

        if (is_string($rawId)) {
            return $this->openAlexWorkKey($rawId);
        }

        $openAlexId = $work->ids()->findByNamespace(WorkIdNamespace::OPENALEX);

        if ($openAlexId !== null) {
            return $this->openAlexWorkKey($openAlexId->value);
        }

        $seedRaw = $this->fetchRawSeedWork($work);

        if (! is_array($seedRaw)) {
            return null;
        }

        $resolvedId = $seedRaw['id'] ?? $seedRaw['ids']['openalex'] ?? null;

        return is_string($resolvedId) ? $this->openAlexWorkKey($resolvedId) : null;
    }

    private function fetchRawSeedWork(ScholarlyWork $work): ?array
    {
        $identifier = $this->singleWorkIdentifierFor($work);

        if ($identifier === null) {
            return null;
        }

        $response = $this->request(
            "{$this->config->baseUrl}/works/{$identifier}",
            $this->mailtoParams(),
        );

        return $response->ok() ? $response->body : null;
    }

    /**
     * @return list<ScholarlyWork>
     */
    private function fetchFilteredWorks(string $filter, int $limit): array
    {
        $collected = [];
        $page = 1;

        while (count($collected) < $limit) {
            $remaining = $limit - count($collected);
            $perPage = min(200, $remaining);
            $params = [
                'filter' => $filter,
                'per-page' => $perPage,
                'page' => $page,
                ...$this->mailtoParams(),
            ];

            $response = $this->request("{$this->config->baseUrl}/works", $params);

            if (! $response->ok()) {
                break;
            }

            $items = $this->extractItems($response->body);

            if ($items === []) {
                break;
            }

            foreach ($items as $raw) {
                if (count($collected) >= $limit) {
                    break 2;
                }

                $collected[] = $this->normalize($raw, $this->snowballQuery());
            }

            if (count($items) < $perPage) {
                break;
            }

            $page++;
        }

        return $collected;
    }

    /**
     * @param list<string> $ids
     * @return list<ScholarlyWork>
     */
    private function fetchWorksByOpenAlexIds(array $ids, int $limit): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn (string $id): ?string => $this->openAlexWorkKey($id), $ids),
        )));

        if ($ids === []) {
            return [];
        }

        $collected = [];

        foreach (array_chunk(array_slice($ids, 0, $limit), 50) as $chunk) {
            $remaining = $limit - count($collected);
            $response = $this->request(
                "{$this->config->baseUrl}/works",
                [
                    'filter' => 'openalex_id:'.implode('|', $chunk),
                    'per-page' => min(count($chunk), $remaining),
                    'page' => 1,
                    ...$this->mailtoParams(),
                ],
            );

            if (! $response->ok()) {
                continue;
            }

            foreach ($this->extractItems($response->body) as $raw) {
                if (count($collected) >= $limit) {
                    break 2;
                }

                $collected[] = $this->normalize($raw, $this->snowballQuery());
            }

            if (count($collected) >= $limit) {
                break;
            }
        }

        return array_slice($collected, 0, $limit);
    }

    /**
     * @return list<string>
     */
    private function referencedWorkIdsFromRawData(ScholarlyWork $work): array
    {
        $rawData = $work->rawData();

        return is_array($rawData) ? $this->referencedWorkIdsFromArray($rawData) : [];
    }

    /**
     * @return list<string>
     */
    private function referencedWorkIdsFromArray(array $raw): array
    {
        $ids = $raw['referenced_works'] ?? [];

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $id): ?string => is_string($id) ? $this->openAlexWorkKey($id) : null,
                $ids,
            ),
        ));
    }

    private function openAlexWorkKey(string $value): ?string
    {
        $value = trim($value);
        $value = preg_replace('/^https?:\/\/openalex\.org\//i', '', $value) ?? $value;
        $value = preg_replace('/^openalex:/i', '', $value) ?? $value;

        if (preg_match('/^(W\d+)$/i', $value, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function mailtoParams(): array
    {
        return ['mailto' => $this->config->mailTo ?? ''];
    }

    private function snowballQuery(): SearchQuery
    {
        return new SearchQuery(
            term: new SearchTerm('snowball'),
            providerAliases: [$this->alias()],
        );
    }
}
