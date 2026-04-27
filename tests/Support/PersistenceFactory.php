<?php

declare(strict_types=1);

namespace Tests\Support;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\CitationNetwork\Domain\CitationGraphId;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\Venue;
use Nexus\Shared\ValueObject\LanguageCode;
use Nexus\Laravel\Model\SlrProject;
use Illuminate\Support\Str;

final class PersistenceFactory
{
    public static function makeWork(
        string $doi = '10.1234/test',
        string $title = 'A Test Work',
        int    $year = 2024,
        int    $citedByCount = 10
    ): ScholarlyWork {
        return ScholarlyWork::reconstitute(
            ids: WorkIdSet::fromArray([
                new WorkId(WorkIdNamespace::DOI, $doi)
            ]),
            title: $title,
            sourceProvider: 'openalex',
            year: $year,
            authors: AuthorList::fromArray([
                new Author(familyName: 'Doe', givenName: 'John')
            ]),
            venue: new Venue(name: 'Test Journal', type: 'journal'),
            abstract: 'This is a test abstract.',
            citedByCount: $citedByCount,
            isRetracted: false
        );
    }

    public static function makeSearchQuery(
        string $projectId,
        string $queryText = 'machine learning'
    ): SearchQuery {
        return new SearchQuery(
            term: new SearchTerm($queryText),
            projectId: $projectId,
            yearRange: YearRange::between(2020, 2024),
            language: LanguageCode::english(),
            maxResults: 100,
            offset: 0,
            includeRawData: false
        );
    }

    public static function makeCluster(
        string $projectId,
        ?ScholarlyWork $seed = null
    ): DedupCluster {
        $seed ??= self::makeWork();
        return DedupCluster::reconstitute(
            id: DedupClusterId::generate(),
            projectId: $projectId,
            representative: $seed,
            members: [$seed],
            strategy: 'default',
            thresholds: ['title' => 95],
            confidence: 0.99
        );
    }

    public static function makeCitationGraph(
        string $projectId,
        ?CitationGraphType $type = null
    ): CitationGraph {
        return CitationGraph::create(
            $type ?? CitationGraphType::CITATION,
            $projectId
        );
    }

    public static function makeProject(string $name = 'Test Project'): SlrProject
    {
        return SlrProject::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'description' => 'A test project for persistence layer.',
            'metadata' => ['key' => 'value']
        ]);
    }
}
