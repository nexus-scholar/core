<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkProviderModel;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Tests\Support\PersistenceFactory;

it('loads distinct project works as screening work inputs with provenance', function (): void {
    $project = PersistenceFactory::makeProject('Screening Source Project');
    $otherProject = PersistenceFactory::makeProject('Other Project');
    $query = makeScreeningSourceQuery($project->id, 'tomato segmentation');
    $otherQuery = makeScreeningSourceQuery($otherProject->id, 'medical segmentation');

    $includedWork = makeScreeningSourceWork('work-1', 'Tomato instance segmentation', 'openalex', [
        'doi' => '10.1000/tomato',
        'openalex' => 'W123',
    ]);
    $otherWork = makeScreeningSourceWork('work-2', 'Retinal vessel segmentation', 'openalex', [
        'doi' => '10.1000/retina',
    ]);

    linkSourceWork($query->id, $includedWork->id, 'openalex', 1);
    linkSourceWork($query->id, $includedWork->id, 'semantic_scholar', 2);
    linkSourceWork($otherQuery->id, $otherWork->id, 'openalex', 1);

    $works = app(ScreeningWorkSourcePort::class)->forProject(
        projectId: $project->id,
        limit: 5,
        queryIds: [$query->id],
    );

    expect($works)->toHaveCount(1)
        ->and($works[0]->id)->toBe('work-1')
        ->and($works[0]->title)->toBe('Tomato instance segmentation')
        ->and($works[0]->sourceProvider)->toBe('openalex')
        ->and($works[0]->identifiers)->toMatchArray([
            'doi' => '10.1000/tomato',
            'openalex' => 'w123',
        ])
        ->and($works[0]->metadata['provider_aliases'])->toContain('openalex', 'semantic_scholar');
});

function makeScreeningSourceQuery(string $projectId, string $queryText): SearchQueryModel
{
    return SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $projectId,
        'query_text' => $queryText,
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.$queryText),
        'status' => 'completed',
    ]);
}

/**
 * @param  array<string, string>  $identifiers
 */
function makeScreeningSourceWork(
    string $id,
    string $title,
    string $provider,
    array $identifiers,
): ScholarlyWorkModel {
    $work = ScholarlyWorkModel::create([
        'id' => $id,
        'title' => $title,
        'abstract' => 'Abstract for '.$title,
        'year' => 2025,
        'venue_name' => 'Plant Methods',
        'retrieved_at' => now(),
    ]);

    foreach ($identifiers as $namespace => $value) {
        WorkExternalIdModel::create([
            'id' => (string) Str::uuid(),
            'work_id' => $id,
            'namespace' => $namespace,
            'value' => strtolower($value),
            'is_primary' => $namespace === 'doi',
        ]);
    }

    WorkProviderModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => $id,
        'provider_alias' => $provider,
        'provider_work_id' => $identifiers[$provider] ?? null,
        'metadata' => ['source' => 'test'],
        'last_seen_at' => now(),
    ]);

    return $work;
}

function linkSourceWork(string $searchQueryId, string $workId, string $provider, int $rank): void
{
    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $searchQueryId,
        'work_id' => $workId,
        'provider_alias' => $provider,
        'provider_work_id' => $provider.'-'.$workId,
        'rank' => $rank,
    ]);
}
