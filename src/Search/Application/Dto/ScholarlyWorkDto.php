<?php

declare(strict_types=1);

namespace Nexus\Search\Application\Dto;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

/**
 * Data Transfer Object for ScholarlyWork to facilitate safe serialization for caching.
 */
final class ScholarlyWorkDto
{
    public static function fromDomain(ScholarlyWork $work): array
    {
        return [
            'ids' => array_map(fn (WorkId $id) => [
                'ns' => $id->namespace->value,
                'val' => $id->value,
            ], $work->ids()->all()),
            'title' => $work->title(),
            'authors' => array_map(fn (Author $au) => [
                'family' => $au->familyName,
                'given' => $au->givenName,
                'orcid' => $au->orcid?->value,
            ], $work->authors()->all()),
            'year' => $work->year(),
            'venue' => $work->venue() ? [
                'name' => $work->venue()->name,
                'issn' => $work->venue()->issn,
                'type' => $work->venue()->type,
            ] : null,
            'abstract' => $work->abstract(),
            'citedByCount' => $work->citedByCount(),
            'isRetracted' => $work->isRetracted(),
            'sourceProvider' => $work->sourceProvider(),
            'retrievedAt' => $work->retrievedAt()->format(\DateTimeInterface::ATOM),
            'rawData' => $work->rawData(),
        ];
    }

    public static function toDomain(array $data): ScholarlyWork
    {
        $ids = WorkIdSet::empty();
        foreach ($data['ids'] as $idData) {
            $ids = $ids->add(new WorkId(WorkIdNamespace::from($idData['ns']), $idData['val']));
        }

        $authors = array_map(fn (array $au) => new Author(
            familyName: $au['family'],
            givenName: $au['given'],
            orcid: isset($au['orcid']) ? new \Nexus\Shared\ValueObject\OrcidId($au['orcid']) : null
        ), $data['authors']);

        $venue = $data['venue'] ? new Venue(
            name: $data['venue']['name'],
            issn: $data['venue']['issn'],
            type: $data['venue']['type'] ?? 'journal'
        ) : null;

        return ScholarlyWork::reconstitute(
            ids: $ids,
            title: $data['title'],
            sourceProvider: $data['sourceProvider'],
            year: $data['year'],
            authors: AuthorList::fromArray($authors),
            venue: $venue,
            abstract: $data['abstract'],
            citedByCount: $data['citedByCount'],
            isRetracted: $data['isRetracted'],
            rawData: $data['rawData']
        );
    }
}
