<?php

declare(strict_types=1);

use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function makeWork(string $doi, string $title = 'Test Work', ?string $abstract = null): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title:          $title,
        sourceProvider: 'test',
        abstract:       $abstract,
    );
}

// ── ScholarlyWork ─────────────────────────────────────────────────────────────

it('identifies_same_work_via_shared_doi', function (): void {
    $a = makeWork('10.1234/abc');
    $b = makeWork('10.1234/abc');
    expect($a->isSameWorkAs($b))->toBeTrue();
});

it('identifies_same_work_via_shared_openalex_id', function (): void {
    $ids = WorkIdSet::fromArray([new WorkId(WorkIdNamespace::OPENALEX, 'w12345')]);
    $a   = ScholarlyWork::reconstitute(ids: $ids, title: 'A', sourceProvider: 'test');
    $b   = ScholarlyWork::reconstitute(ids: $ids, title: 'B', sourceProvider: 'test2');
    expect($a->isSameWorkAs($b))->toBeTrue();
});

it('returns_false_for_works_with_no_shared_ids', function (): void {
    $a = makeWork('10.1234/aaa');
    $b = makeWork('10.1234/bbb');
    expect($a->isSameWorkAs($b))->toBeFalse();
});

it('merges_work_ids_from_both_sides', function (): void {
    $a = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/abc')]),
        title:          'Work A',
        sourceProvider: 'openalex',
    );
    $b = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::DOI, '10.1234/abc'),
            new WorkId(WorkIdNamespace::ARXIV, '2301.12345'),
        ]),
        title:          'Work B',
        sourceProvider: 'arxiv',
    );

    $merged = $a->mergeWith($b);
    expect($merged->ids()->count())->toBeGreaterThanOrEqual(2);
});

it('does_not_overwrite_existing_fields_during_merge', function (): void {
    $a = ScholarlyWork::reconstitute(
        ids:      WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/abc')]),
        title:    'Original Title',
        sourceProvider: 'openalex',
        abstract: 'Original abstract',
    );
    $b = ScholarlyWork::reconstitute(
        ids:      WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/abc')]),
        title:    'Other Title',
        sourceProvider: 'crossref',
        abstract: 'Other abstract',
    );

    $merged = $a->mergeWith($b);
    expect($merged->abstract())->toBe('Original abstract');
});

it('merges_abstract_from_other_when_own_is_null', function (): void {
    $a = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/abc')]),
        title:          'Work A',
        sourceProvider: 'openalex',
        abstract:       null,
    );
    $b = makeWork('10.1234/abc', 'Work B', 'Filled abstract');

    $merged = $a->mergeWith($b);
    expect($merged->abstract())->toBe('Filled abstract');
});

it('stores_raw_data_only_when_provided', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/y')]),
        title:          'Test',
        sourceProvider: 'test',
        rawData:        ['key' => 'value'],
    );
    expect($work->rawData())->toBe(['key' => 'value']);
});

it('returns_null_raw_data_by_default', function (): void {
    $work = makeWork('10.x/z');
    expect($work->rawData())->toBeNull();
});

it('scores_completeness_higher_with_more_fields', function (): void {
    $bare = makeWork('10.x/bare');
    $rich = makeWork('10.x/rich', abstract: 'Has abstract');

    expect($rich->completenessScore())->toBeGreaterThan($bare->completenessScore());
});

it('is_a_preprint_when_from_arxiv', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.12345')]),
        title:          'Preprint',
        sourceProvider: 'arxiv',
    );
    expect($work->isPreprint())->toBeTrue();
});

// ── CorpusSlice ───────────────────────────────────────────────────────────────

it('starts_empty', function (): void {
    expect(CorpusSlice::empty()->count())->toBe(0);
});

it('adds_work_without_duplicating', function (): void {
    $slice = CorpusSlice::empty();
    $work  = makeWork('10.1234/abc');
    $slice->addWork($work);
    $slice->addWork($work);
    expect($slice->count())->toBe(1);
});

it('merges_instead_of_duplicating_same_work', function (): void {
    $slice  = CorpusSlice::empty();
    $doi    = '10.1234/shared';
    $fromOA = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title:          'From OpenAlex',
        sourceProvider: 'openalex',
        abstract:       null,
    );
    $fromCR = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title:          'From Crossref',
        sourceProvider: 'crossref',
        abstract:       'Has abstract now',
    );

    $slice->addWork($fromOA);
    $slice->addWork($fromCR);

    expect($slice->count())->toBe(1);
    expect($slice->all()[0]->abstract())->toBe('Has abstract now');
});

it('contains_added_work', function (): void {
    $slice = CorpusSlice::empty();
    $work  = makeWork('10.1234/abc');
    $slice->addWork($work);
    expect($slice->contains($work))->toBeTrue();
});

it('does_not_contain_work_with_no_shared_ids', function (): void {
    $slice = CorpusSlice::empty();
    $slice->addWork(makeWork('10.1234/aaa'));
    expect($slice->contains(makeWork('10.1234/bbb')))->toBeFalse();
});

it('merges_two_slices_without_duplication', function (): void {
    $a = CorpusSlice::fromWorks(makeWork('10.1234/aaa'), makeWork('10.1234/bbb'));
    $b = CorpusSlice::fromWorks(makeWork('10.1234/bbb'), makeWork('10.1234/ccc'));
    $merged = $a->merge($b);
    expect($merged->count())->toBe(3);
});

it('returns_correct_count', function (): void {
    $slice = CorpusSlice::fromWorks(
        makeWork('10.x/1'),
        makeWork('10.x/2'),
        makeWork('10.x/3'),
    );
    expect($slice->count())->toBe(3);
});

it('subtracts_known_works', function (): void {
    $all     = CorpusSlice::fromWorks(makeWork('10.x/1'), makeWork('10.x/2'), makeWork('10.x/3'));
    $known   = CorpusSlice::fromWorks(makeWork('10.x/1'));
    $result  = $all->subtract($known);
    expect($result->count())->toBe(2);
});

it('filters_by_predicate', function (): void {
    $retracted = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/r')]),
        title:          'Retracted',
        sourceProvider: 'test',
        isRetracted:    true,
    );
    $slice = CorpusSlice::fromWorks(makeWork('10.x/1'), $retracted);

    $filtered = $slice->filter(fn ($w) => ! $w->isRetracted());
    expect($filtered->count())->toBe(1);
});

it('excludes_retracted_when_asked', function (): void {
    $retracted = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/r')]),
        title:          'Retracted',
        sourceProvider: 'test',
        isRetracted:    true,
    );
    $slice = CorpusSlice::fromWorks(makeWork('10.x/1'), $retracted);
    expect($slice->withoutRetracted()->count())->toBe(1);
});

// ── Added Tests for 100% Coverage ─────────────────────────────────────────────

it('throws_on_empty_title', function (): void {
    expect(fn () => ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/1')]),
        title: '   ',
        sourceProvider: 'test'
    ))->toThrow(\InvalidArgumentException::class, 'must not be empty');
});

it('returns_all_properties_correctly', function (): void {
    $venue = new \Nexus\Shared\ValueObject\Venue('Nature', null, 'journal');
    $author = new \Nexus\Shared\ValueObject\Author('Smith', 'John');
    
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/prop')]),
        title:          'Full Props',
        sourceProvider: 'crossref',
        year:           2025,
        authors:        \Nexus\Shared\ValueObject\AuthorList::fromArray([$author]),
        venue:          $venue,
        citedByCount:   42,
    );

    expect($work->year())->toBe(2025);
    expect($work->venue())->toBe($venue);
    expect($work->citedByCount())->toBe(42);
    expect($work->sourceProvider())->toBe('crossref');
    expect($work->retrievedAt())->toBeInstanceOf(\DateTimeImmutable::class);
    expect($work->authors()->count())->toBe(1);
});

it('manipulates_raw_data', function (): void {
    $work = makeWork('10.x/raw');
    expect($work->rawData())->toBeNull();

    $withRaw = $work->withRawData(['foo' => 'bar']);
    expect($withRaw->rawData())->toBe(['foo' => 'bar']);
    
    $withoutRaw = $withRaw->withoutRawData();
    expect($withoutRaw->rawData())->toBeNull();
});

it('calculates_completeness_score_with_all_bonuses', function (): void {
    $author = new \Nexus\Shared\ValueObject\Author('Smith', 'John', new \Nexus\Shared\ValueObject\OrcidId('0000-0002-1825-0097'));
    
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/score')]), // 2
        title:          'Score',
        sourceProvider: 'crossref',
        year:           2025, // 1
        authors:        \Nexus\Shared\ValueObject\AuthorList::fromArray([$author]), // 1 + 1 (orcid)
        venue:          new \Nexus\Shared\ValueObject\Venue('Test'), // 1
        abstract:       'Abstract', // 2
        citedByCount:   10, // 1
        isRetracted:    false // 1
    ); // Total 10

    expect($work->completenessScore())->toBe(10);
});

it('finds_slice_by_id_and_title', function (): void {
    $doi = new WorkId(WorkIdNamespace::DOI, '10.x/find');
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([$doi]),
        title:          'Unique Title',
        sourceProvider: 'test'
    );
    
    $slice = CorpusSlice::fromWorks($work);
    
    expect($slice->findById($doi)?->title())->toBe('Unique Title');
    expect($slice->findById(new WorkId(WorkIdNamespace::DOI, '10.x/missing')))->toBeNull();
    
    expect($slice->findByTitle('Unique Title')?->title())->toBe('Unique Title');
    expect($slice->findByTitle('unique title')?->title())->toBe('Unique Title'); // case insensitive
    expect($slice->findByTitle('Missing Title'))->toBeNull();
});

it('checks_emptiness', function (): void {
    expect(CorpusSlice::empty()->isEmpty())->toBeTrue();
    $slice = CorpusSlice::fromWorks(makeWork('10.x/1'));
    expect($slice->isEmpty())->toBeFalse();
});

it('sorts_by_year_and_cited_by_count', function (): void {
    $work1 = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/1')]),
        title: 'Work 1',
        sourceProvider: 'test',
        year: 2020,
        citedByCount: 10
    );
    $work2 = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/2')]),
        title: 'Work 2',
        sourceProvider: 'test',
        year: 2022,
        citedByCount: 5
    );
    $work3 = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/3')]),
        title: 'Work 3',
        sourceProvider: 'test',
        year: null,
        citedByCount: null
    );

    $slice = CorpusSlice::fromWorks($work1, $work2, $work3);

    $byYearDesc = $slice->sortByYear(true)->all();
    expect($byYearDesc[0]->year())->toBe(2022);
    expect($byYearDesc[1]->year())->toBe(2020);
    expect($byYearDesc[2]->year())->toBeNull();

    $byYearAsc = $slice->sortByYear(false)->all();
    expect($byYearAsc[0]->year())->toBeNull();
    expect($byYearAsc[1]->year())->toBe(2020);
    expect($byYearAsc[2]->year())->toBe(2022);

    $byCitedDesc = $slice->sortByCitedByCount(true)->all();
    expect($byCitedDesc[0]->citedByCount())->toBe(10);
    expect($byCitedDesc[1]->citedByCount())->toBe(5);
    expect($byCitedDesc[2]->citedByCount())->toBeNull();
    
    $byCitedAsc = $slice->sortByCitedByCount(false)->all();
    expect($byCitedAsc[0]->citedByCount())->toBeNull();
    expect($byCitedAsc[1]->citedByCount())->toBe(5);
    expect($byCitedAsc[2]->citedByCount())->toBe(10);
});

it('filters_only_retracted', function (): void {
    $retracted = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/r')]),
        title:          'Retracted',
        sourceProvider: 'test',
        isRetracted:    true,
    );
    $slice = CorpusSlice::fromWorks(makeWork('10.x/1'), $retracted);
    
    $retractedSlice = $slice->retracted();
    expect($retractedSlice->count())->toBe(1);
    expect($retractedSlice->all()[0]->isRetracted())->toBeTrue();
});

