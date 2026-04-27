<?php

declare(strict_types=1);

use Nexus\Search\Domain\Port\SearchQueryRepositoryPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Domain\YearRange;
use Nexus\Search\Domain\ProviderProgress;
use Nexus\Shared\ValueObject\LanguageCode;
use Tests\Support\PersistenceFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->repo = app(SearchQueryRepositoryPort::class);
    $this->project = PersistenceFactory::makeProject();
});

it('returns null for unknown search query id', function () {
    expect($this->repo->findById('Qunknown'))->toBeNull();
});

it('saves a search query and retrieves it with all fields intact', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id, 'quantum computing');
    
    $this->repo->save($query);
    
    $loaded = $this->repo->findById($query->id);
    
    expect($loaded)->not->toBeNull()
        ->and($loaded->id)->toBe($query->id)
        ->and($loaded->term->value)->toBe('quantum computing')
        ->and($loaded->yearRange->from)->toBe(2020)
        ->and($loaded->yearRange->to)->toBe(2024)
        ->and($loaded->language->value)->toBe('en');
});

it('save is idempotent: saving the same query twice does not duplicate rows', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    
    $this->repo->save($query);
    
    $count = DB::table('search_queries')->count();
    
    $this->repo->save($query);
    expect(DB::table('search_queries')->count())->toBe($count);
});

it('save updates status when query status changes', function () {
    $this->markTestSkipped('bug: SearchQuery domain object does not have a status property to change');
});

it('records provider progress for a single provider', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $progress = new ProviderProgress(totalRaw: 10, totalUnique: 8, durationMs: 500);
    
    $this->repo->recordProviderProgress($query->id, 'openalex', $progress);
    
    $row = DB::table('search_query_providers')
        ->where('search_query_id', $query->id)
        ->where('provider_alias', 'openalex')
        ->first();
        
    expect($row)->not->toBeNull()
        ->and($row->total_raw)->toBe(10)
        ->and($row->total_unique)->toBe(8)
        ->and($row->duration_ms)->toBe(500);
});

it('records provider progress for multiple providers independently', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key_multi',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $progress1 = new ProviderProgress(totalRaw: 10, totalUnique: 8, durationMs: 500);
    $progress2 = new ProviderProgress(totalRaw: 20, totalUnique: 15, durationMs: 800);
    
    $this->repo->recordProviderProgress($query->id, 'openalex', $progress1);
    $this->repo->recordProviderProgress($query->id, 'arxiv', $progress2);
    
    expect(DB::table('search_query_providers')->where('search_query_id', $query->id)->count())->toBe(2);
});

it('updating provider progress is idempotent on same provider_alias', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key_idempotent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $progress = new ProviderProgress(totalRaw: 10, totalUnique: 8, durationMs: 500);
    
    $this->repo->recordProviderProgress($query->id, 'openalex', $progress);
    $count = DB::table('search_query_providers')->count();
    
    $this->repo->recordProviderProgress($query->id, 'openalex', $progress);
    expect(DB::table('search_query_providers')->count())->toBe($count);
});

it('records error message on failed provider progress', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key_error',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $progress = new ProviderProgress(totalRaw: 0, totalUnique: 0, durationMs: 100, errorMessage: 'API Timeout');
    
    $this->repo->recordProviderProgress($query->id, 'openalex', $progress);
    
    $row = DB::table('search_query_providers')->where('provider_alias', 'openalex')->first();
    expect($row->error_message)->toBe('API Timeout');
});

it('links a work to a query', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key_link',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $work = PersistenceFactory::makeWork();
    app(\Nexus\Search\Domain\Port\WorkRepositoryPort::class)->save($work);
    
    $this->repo->linkWorkToQuery(
        $query->id, 
        $work->primaryId()->toString(), 
        'openalex', 
        'W123', 
        1
    );
    
    $this->assertDatabaseHas('query_works', [
        'search_query_id' => $query->id,
        'work_id' => $work->primaryId()->value, // DB uses bare value
        'provider_alias' => 'openalex',
        'provider_work_id' => 'W123',
        'rank' => 1
    ]);
});

it('linking the same work+provider+alias twice does not create duplicate rows', function () {
    $query = PersistenceFactory::makeSearchQuery($this->project->id);
    DB::table('search_queries')->insert([
        'id' => $query->id,
        'project_id' => $this->project->id,
        'query_text' => $query->term->value,
        'cache_key' => 'test_cache_key_link_idempotent',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $work = PersistenceFactory::makeWork();
    app(\Nexus\Search\Domain\Port\WorkRepositoryPort::class)->save($work);
    
    $this->repo->linkWorkToQuery($query->id, $work->primaryId()->toString(), 'openalex', 'W123', 1);
    $count = DB::table('query_works')->count();
    
    $this->repo->linkWorkToQuery($query->id, $work->primaryId()->toString(), 'openalex', 'W123', 1);
    expect(DB::table('query_works')->count())->toBe($count);
});

it('findByProject returns all queries for a project ordered newest first', function () {
    $q1 = PersistenceFactory::makeSearchQuery($this->project->id, 'first');
    $q2 = PersistenceFactory::makeSearchQuery($this->project->id, 'second');
    
    DB::table('search_queries')->insert([
        'id' => $q1->id,
        'project_id' => $this->project->id,
        'query_text' => $q1->term->value,
        'cache_key' => 'ck1',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);
    
    DB::table('search_queries')->insert([
        'id' => $q2->id,
        'project_id' => $this->project->id,
        'query_text' => $q2->term->value,
        'cache_key' => 'ck2',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    $results = $this->repo->findByProject($this->project->id);
    
    expect($results)->toHaveCount(2)
        ->and($results[0]->id)->toBe($q2->id)
        ->and($results[1]->id)->toBe($q1->id);
});

it('findByProject returns empty array for project with no queries', function () {
    expect($this->repo->findByProject((string) Str::uuid()))->toBe([]);
});
