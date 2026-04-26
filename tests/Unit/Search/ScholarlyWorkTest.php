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
