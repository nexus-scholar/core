<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Provider;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\OrcidId;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

final class OpenAlexAdapter extends BaseProviderAdapter
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

        $response = $this->request("{$this->config->baseUrl}/works", $params);

        if (! $response->ok()) {
            return [];
        }

        $items = $this->extractItems($response->body);

        return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
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
}
