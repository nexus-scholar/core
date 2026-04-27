<?php

declare(strict_types=1);

use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\Venue;
use Tests\Support\PersistenceFactory;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->repo = app(WorkRepositoryPort::class);
});

it('returns null for unknown work id', function () {
    $id = new WorkId(WorkIdNamespace::DOI, '10.0000/unknown');
    expect($this->repo->findById($id))->toBeNull();
});

it('saves a work and retrieves it by id with all scalar fields intact', function () {
    $work = PersistenceFactory::makeWork(doi: '10.1111/scalar', title: 'Scalar Test', year: 2021);
    
    $this->repo->save($work);
    
    $loaded = $this->repo->findById($work->primaryId());
    
    expect($loaded)->not->toBeNull()
        ->and($loaded->title())->toBe('Scalar Test')
        ->and($loaded->year())->toBe(2021)
        ->and($loaded->abstract())->toBe($work->abstract())
        ->and($loaded->citedByCount())->toBe($work->citedByCount())
        ->and($loaded->isRetracted())->toBe($work->isRetracted());
});

it('saves a work with multiple external ids and retrieves them all', function () {
    $ids = WorkIdSet::fromArray([
        new WorkId(WorkIdNamespace::DOI, '10.2222/multi'),
        new WorkId(WorkIdNamespace::ARXIV, '2101.12345'),
        new WorkId(WorkIdNamespace::OPENALEX, 'W123456789')
    ]);
    
    $work = ScholarlyWork::reconstitute(
        ids: $ids,
        title: 'Multi ID Test',
        sourceProvider: 'test'
    );
    
    $this->repo->save($work);
    
    $loaded = $this->repo->findById($work->primaryId());
    
    expect($loaded->ids()->all())->toHaveCount(4); // 3 external + 1 internal
    expect($loaded->ids()->findByNamespace(WorkIdNamespace::ARXIV)->value)->toBe('2101.12345');
    expect($loaded->ids()->findByNamespace(WorkIdNamespace::OPENALEX)->value)->toBe('w123456789'); // normalized
});

it('marks exactly one external id as primary', function () {
    $doi = new WorkId(WorkIdNamespace::DOI, '10.3333/primary');
    $arxiv = new WorkId(WorkIdNamespace::ARXIV, '2102.12345');
    
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([$arxiv, $doi]),
        title: 'Primary Test',
        sourceProvider: 'test'
    );
    
    expect($work->primaryId()->equals($doi))->toBeTrue();
    
    $this->repo->save($work);
    
    $loaded = $this->repo->findById($work->primaryId());
    expect($loaded->primaryId()->equals($doi))->toBeTrue();
    
    $primaryCount = DB::table('work_external_ids')
        ->where('work_id', $work->primaryId()->value) // Use bare value for DB query
        ->where('is_primary', true)
        ->count();
    expect($primaryCount)->toBe(1);
});

it('saves a work with a full author list and reconstructs author order', function () {
    $authors = AuthorList::fromArray([
        new Author(familyName: 'Alpha', givenName: 'A'),
        new Author(familyName: 'Beta', givenName: 'B'),
        new Author(familyName: 'Gamma', givenName: 'G'),
    ]);
    
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.4444/authors')]),
        title: 'Author Order Test',
        sourceProvider: 'test',
        authors: $authors
    );
    
    $this->repo->save($work);
    
    $loaded = $this->repo->findById($work->primaryId());
    
    expect($loaded->authors()->all())->toHaveCount(3)
        ->and($loaded->authors()->get(0)->familyName)->toBe('Alpha')
        ->and($loaded->authors()->get(1)->familyName)->toBe('Beta')
        ->and($loaded->authors()->get(2)->familyName)->toBe('Gamma');
});

it('saves a work with venue data and reconstructs venue', function () {
    $venue = new Venue(name: 'Nature', issn: '1476-4687', type: 'journal');
    
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.5555/venue')]),
        title: 'Venue Test',
        sourceProvider: 'test',
        venue: $venue
    );
    
    $this->repo->save($work);
    
    $loaded = $this->repo->findById($work->primaryId());
    
    expect($loaded->venue())->not->toBeNull()
        ->and($loaded->venue()->name)->toBe('Nature')
        ->and($loaded->venue()->issn)->toBe('1476-4687')
        ->and($loaded->venue()->type)->toBe('journal');
});

it('save is idempotent: saving the same work twice does not duplicate rows', function () {
    $work = PersistenceFactory::makeWork(doi: '10.6666/idempotent');
    
    $this->repo->save($work);
    $countWorks = DB::table('scholarly_works')->count();
    $countIds = DB::table('work_external_ids')->count();
    $countAuthors = DB::table('work_authors')->count();
    
    $this->repo->save($work);
    
    expect(DB::table('scholarly_works')->count())->toBe($countWorks);
    expect(DB::table('work_external_ids')->count())->toBe($countIds);
    expect(DB::table('work_authors')->count())->toBe($countAuthors);
});

it('save updates title when called a second time with changed title', function () {
    $work = PersistenceFactory::makeWork(doi: '10.7777/update', title: 'Original Title');
    $this->repo->save($work);
    
    $updatedWork = ScholarlyWork::reconstitute(
        ids: $work->ids(),
        title: 'Updated Title',
        sourceProvider: 'test'
    );
    
    $this->repo->save($updatedWork);
    
    $loaded = $this->repo->findById($work->primaryId());
    expect($loaded->title())->toBe('Updated Title');
});

it('re-syncs external ids on second save: removed id is gone, new id is present', function () {
    $id1 = new WorkId(WorkIdNamespace::DOI, '10.8888/sync1');
    $id2 = new WorkId(WorkIdNamespace::ARXIV, '2103.12345');
    
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([$id1, $id2]),
        title: 'Sync IDs Test',
        sourceProvider: 'test'
    );
    $this->repo->save($work);
    
    $id3 = new WorkId(WorkIdNamespace::S2, 'S2_ID_123');
    $updatedWork = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([$id1, $id3]), // removed id2, added id3
        title: 'Sync IDs Test',
        sourceProvider: 'test'
    );
    
    $this->repo->save($updatedWork);
    
    $loaded = $this->repo->findById($id1);
    expect($loaded->ids()->all())->toHaveCount(3); // 2 external + 1 internal
    expect($loaded->ids()->findByNamespace(WorkIdNamespace::ARXIV))->toBeNull();
    expect($loaded->ids()->findByNamespace(WorkIdNamespace::S2))->not->toBeNull();
});

it('re-syncs authors on second save: order change is persisted', function () {
    $authorA = new Author(familyName: 'Alpha');
    $authorB = new Author(familyName: 'Beta');
    
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.9999/authorsync')]),
        title: 'Sync Authors Test',
        sourceProvider: 'test',
        authors: AuthorList::fromArray([$authorA, $authorB])
    );
    $this->repo->save($work);
    
    $updatedWork = ScholarlyWork::reconstitute(
        ids: $work->ids(),
        title: 'Sync Authors Test',
        sourceProvider: 'test',
        authors: AuthorList::fromArray([$authorB, $authorA]) // Reversed
    );
    
    $this->repo->save($updatedWork);
    
    $loaded = $this->repo->findById($work->primaryId());
    expect($loaded->authors()->get(0)->familyName)->toBe('Beta');
    expect($loaded->authors()->get(1)->familyName)->toBe('Alpha');
});

it('findManyByIds returns all requested works in a single call', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.1010/batch1', title: 'Batch 1');
    $work2 = PersistenceFactory::makeWork(doi: '10.1010/batch2', title: 'Batch 2');
    $this->repo->save($work1);
    $this->repo->save($work2);
    
    $results = $this->repo->findManyByIds([$work1->primaryId(), $work2->primaryId()]);
    
    expect($results)->toHaveCount(2);
    expect($results[$work1->primaryId()->toString()]->title())->toBe('Batch 1');
    expect($results[$work2->primaryId()->toString()]->title())->toBe('Batch 2');
});

it('findManyByIds returns empty array for empty input', function () {
    expect($this->repo->findManyByIds([]))->toBe([]);
});

it('findManyByIds silently omits ids that do not exist', function () {
    $work1 = PersistenceFactory::makeWork(doi: '10.1010/exists');
    $this->repo->save($work1);
    
    $unknownId = new WorkId(WorkIdNamespace::DOI, '10.0000/missing');
    
    $results = $this->repo->findManyByIds([$work1->primaryId(), $unknownId]);
    
    expect($results)->toHaveCount(1);
    expect($results)->toHaveKey($work1->primaryId()->toString());
});

it('findManyByIds keys results by WorkId string', function () {
    $work = PersistenceFactory::makeWork(doi: '10.1010/keyed');
    $this->repo->save($work);
    
    $results = $this->repo->findManyByIds([$work->primaryId()]);
    
    expect(array_keys($results)[0])->toBe($work->primaryId()->toString());
});

it('deleting a work cascades to work_external_ids', function () {
    $work = PersistenceFactory::makeWork(doi: '10.1212/cascade1');
    $this->repo->save($work);
    
    $workIdStr = $work->primaryId()->value; // DB uses bare value
    
    DB::table('scholarly_works')->where('id', $workIdStr)->delete();
    
    $this->assertDatabaseMissing('work_external_ids', [
        'work_id' => $workIdStr
    ]);
});

it('deleting a work cascades to work_authors', function () {
    $work = PersistenceFactory::makeWork(doi: '10.1212/cascade2');
    $this->repo->save($work);
    
    $workIdStr = $work->primaryId()->value; // DB uses bare value
    
    DB::table('scholarly_works')->where('id', $workIdStr)->delete();
    
    $this->assertDatabaseMissing('work_authors', [
        'work_id' => $workIdStr
    ]);
});
