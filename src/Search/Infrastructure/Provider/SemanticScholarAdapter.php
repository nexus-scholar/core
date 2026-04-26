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

final class SemanticScholarAdapter extends BaseProviderAdapter
{
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
        ], true);
    }

    public function search(SearchQuery $query): array
    {
        $params = array_merge(
            [
                'query'  => $query->term->value,
                'fields' => 'paperId,externalIds,title,abstract,year,venue,authors,citationCount,isOpenAccess',
            ],
            $this->paginationParams($query),
        );

        $headers = [];

        if ($this->config->apiKey !== null) {
            $headers['x-api-key'] = $this->config->apiKey;
        }

        $response = $this->request(
            "{$this->config->baseUrl}/graph/v1/paper/search",
            $params,
            $headers
        );

        if (! $response->ok()) {
            return [];
        }

        $items = $this->extractItems($response->body);

        return array_map(fn (array $raw) => $this->normalize($raw, $query), $items);
    }

    public function fetchById(WorkId $id): ?ScholarlyWork
    {
        $identifier = match ($id->namespace) {
            WorkIdNamespace::DOI   => "DOI:{$id->value}",
            WorkIdNamespace::S2    => $id->value,
            WorkIdNamespace::ARXIV => "ARXIV:{$id->value}",
            default                => null,
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
            ['fields' => 'paperId,externalIds,title,abstract,year,venue,authors,citationCount'],
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

        if (! empty($raw['externalIds']['DOI'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::DOI, $raw['externalIds']['DOI']));
        }

        if (! empty($raw['externalIds']['ArXiv'])) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::ARXIV, $raw['externalIds']['ArXiv']));
        }

        $title   = $this->extractString($raw, 'title') ?? 'Unknown Title';
        $year    = $this->extractInt($raw, 'year');
        $cited   = $this->extractInt($raw, 'citationCount');
        $abstract = $this->extractString($raw, 'abstract');

        $venue = null;
        $venueName = $this->extractString($raw, 'venue');

        if ($venueName !== null) {
            $venue = new Venue(name: $venueName);
        }

        $authors = [];

        foreach ($this->extractArray($raw, 'authors') as $authorRaw) {
            $name = $authorRaw['name'] ?? null;

            if ($name === null) {
                continue;
            }

            $parts  = explode(' ', $name, 2);
            $given  = count($parts) === 2 ? $parts[0] : null;
            $family = count($parts) === 2 ? $parts[1] : $parts[0];

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
        return [
            'limit'  => $query->maxResults,
            'offset' => $query->offset,
        ];
    }

    protected function extractItems(array $body): array
    {
        return $body['data'] ?? [];
    }
}
