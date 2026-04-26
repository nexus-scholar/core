<?php

declare(strict_types=1);

use Nexus\Deduplication\Application\DeduplicateCorpus;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Infrastructure\CompletenessElectionPolicy;
use Nexus\Deduplication\Infrastructure\DoiMatchPolicy;
use Nexus\Deduplication\Infrastructure\FingerprintPolicy;
use Nexus\Deduplication\Infrastructure\TitleFuzzyPolicy;
use Nexus\Deduplication\Infrastructure\TitleNormalizer;
use Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\Author;
use Nexus\Shared\ValueObject\AuthorList;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeDeduplicatable(
    string $doi,
    string $title = 'Test Work',
    ?int $year = null,
    string $provider = 'openalex',
    ?string $abstract = null,
): ScholarlyWork {
    return ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title:          $title,
        sourceProvider: $provider,
        year:           $year,
        abstract:       $abstract,
    );
}

function makeHandler(array $extraPolicies = []): DeduplicateCorpusHandler
{
    $normalizer = new TitleNormalizer();

    return new DeduplicateCorpusHandler(
        policies: array_merge([
            new DoiMatchPolicy(),
            new NamespaceMatchPolicy(WorkIdNamespace::ARXIV),
            new TitleFuzzyPolicy($normalizer),
            new FingerprintPolicy($normalizer),
        ], $extraPolicies),
        electionPolicy: new CompletenessElectionPolicy(),
    );
}

// ── DoiMatchPolicy ────────────────────────────────────────────────────────────

it('detects_two_works_with_identical_doi', function (): void {
    $a = makeDeduplicatable('10.1234/abc');
    $b = makeDeduplicatable('10.1234/abc');

    $policy = new DoiMatchPolicy();
    $dupes  = $policy->detect([$a, $b]);

    expect($dupes)->toHaveCount(1);
    expect($dupes[0]->reason->value)->toBe('doi_match');
    expect($dupes[0]->confidence)->toBe(1.0);
});

it('normalizes_doi_before_comparing', function (): void {
    // Both should normalize to the same DOI value
    $a = makeDeduplicatable('https://doi.org/10.1234/abc');
    $b = makeDeduplicatable('doi:10.1234/abc');

    $policy = new DoiMatchPolicy();
    $dupes  = $policy->detect([$a, $b]);

    expect($dupes)->toHaveCount(1);
});

it('ignores_works_without_doi', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.12345')]),
        title:          'No DOI',
        sourceProvider: 'arxiv',
    );

    $policy = new DoiMatchPolicy();
    expect($policy->detect([$work]))->toBe([]);
});

it('returns_empty_when_all_dois_are_unique', function (): void {
    $works = [
        makeDeduplicatable('10.1234/aaa'),
        makeDeduplicatable('10.1234/bbb'),
        makeDeduplicatable('10.1234/ccc'),
    ];

    $policy = new DoiMatchPolicy();
    expect($policy->detect($works))->toBe([]);
});

// ── DedupCluster ──────────────────────────────────────────────────────────────

it('starts_with_single_seed_as_representative', function (): void {
    $seed    = makeDeduplicatable('10.x/seed');
    $cluster = \Nexus\Deduplication\Domain\DedupCluster::startWith($seed);

    expect($cluster->size())->toBe(1);
    expect($cluster->representative()->primaryId()?->toString())
        ->toBe($seed->primaryId()->toString());
});

it('absorbs_a_duplicate_work', function (): void {
    $seed  = makeDeduplicatable('10.x/seed');
    $other = makeDeduplicatable('10.x/other');

    $cluster  = \Nexus\Deduplication\Domain\DedupCluster::startWith($seed);
    $evidence = new \Nexus\Deduplication\Domain\Duplicate(
        primaryId:   $seed->primaryId(),
        secondaryId: $other->primaryId(),
        reason:      \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH,
        confidence:  1.0,
    );

    $cluster->absorb($other, $evidence);

    expect($cluster->size())->toBe(2);
});

it('size_grows_on_absorb', function (): void {
    $seed    = makeDeduplicatable('10.x/a');
    $cluster = \Nexus\Deduplication\Domain\DedupCluster::startWith($seed);

    for ($i = 1; $i <= 3; $i++) {
        $work = makeDeduplicatable("10.x/b{$i}");
        $ev   = new \Nexus\Deduplication\Domain\Duplicate(
            primaryId:   $seed->primaryId(),
            secondaryId: $work->primaryId(),
            reason:      \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH,
            confidence:  1.0,
        );
        $cluster->absorb($work, $ev);
    }

    expect($cluster->size())->toBe(4);
});

it('collects_all_dois_from_all_members', function (): void {
    $seed = makeDeduplicatable('10.x/a');
    $b    = makeDeduplicatable('10.x/b');
    $cluster = \Nexus\Deduplication\Domain\DedupCluster::startWith($seed);
    $ev = new \Nexus\Deduplication\Domain\Duplicate(
        primaryId:   $seed->primaryId(),
        secondaryId: $b->primaryId(),
        reason:      \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH,
        confidence:  1.0,
    );
    $cluster->absorb($b, $ev);

    expect($cluster->allDois())->toContain('10.x/a');
    expect($cluster->allDois())->toContain('10.x/b');
});

it('elects_most_complete_work_as_representative', function (): void {
    $bare = makeDeduplicatable('10.x/bare', abstract: null);
    $rich = makeDeduplicatable('10.x/rich', abstract: 'Has abstract');

    $cluster = \Nexus\Deduplication\Domain\DedupCluster::startWith($bare);
    $ev = new \Nexus\Deduplication\Domain\Duplicate(
        primaryId:   $bare->primaryId(),
        secondaryId: $rich->primaryId(),
        reason:      \Nexus\Deduplication\Domain\DuplicateReason::DOI_MATCH,
        confidence:  1.0,
    );
    $cluster->absorb($rich, $ev);
    $cluster->electRepresentative(new CompletenessElectionPolicy());

    expect($cluster->representative()->hasAbstract())->toBeTrue();
});

// ── DeduplicateCorpusHandler ──────────────────────────────────────────────────

it('clusters_two_works_with_same_doi_into_one_cluster', function (): void {
    $workA = makeDeduplicatable('10.1234/abc', 'Work A');
    $workB = makeDeduplicatable('10.1234/abc', 'Work B');

    // Bypass CorpusSlice's addWork deduplication to present two distinct
    // objects with the same DOI to the handler.
    $corpus = CorpusSlice::fromWorksUnsafe($workA, $workB);

    // Now the handler receives exactly 2 identical-DOI works
    $result = makeHandler()->handle(new DeduplicateCorpus($corpus));

    expect($result->inputCount)->toBe(2);
    expect($result->uniqueCount)->toBe(1);
    expect($result->clusters->count())->toBe(1);
});

it('clusters_transitively_via_union_find', function (): void {
    // A shares DOI with B; B shares arXiv with C → all three in one cluster
    $arxivId = '2301.99999';
    $a = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/abc')]),
        title:          'Work A', sourceProvider: 'openalex',
    );
    $b = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::DOI, '10.x/abc'),
            new WorkId(WorkIdNamespace::ARXIV, $arxivId),
        ]),
        title:          'Work B', sourceProvider: 'crossref',
    );
    $c = ScholarlyWork::reconstitute(
        ids:            WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, $arxivId)]),
        title:          'Work C', sourceProvider: 'arxiv',
    );

    $corpus = CorpusSlice::fromWorks($a, $b, $c);
    $result = makeHandler()->handle(new DeduplicateCorpus($corpus));

    expect($result->uniqueCount)->toBeLessThan(3);
});

it('reports_correct_duplicate_count', function (): void {
    $corpus = CorpusSlice::fromWorks(
        makeDeduplicatable('10.x/1', 'Work Alpha'),
        makeDeduplicatable('10.x/2', 'Work Beta'),
        makeDeduplicatable('10.x/3', 'Work Gamma'),
    );
    $duplicate = CorpusSlice::fromWorks(makeDeduplicatable('10.x/1', 'Work Alpha'));
    $merged = $corpus->merge($duplicate);

    $result = makeHandler()->handle(new DeduplicateCorpus($merged));
    expect($result->inputCount)->toBe(3);
    expect($result->uniqueCount)->toBe(3);
    expect($result->duplicatesRemoved)->toBe(0);
});

it('returns_singleton_clusters_for_unique_works', function (): void {
    $corpus = CorpusSlice::fromWorks(
        makeDeduplicatable('10.x/1', 'Unique Work Alpha'),
        makeDeduplicatable('10.x/2', 'Unique Work Beta'),
        makeDeduplicatable('10.x/3', 'Unique Work Gamma'),
    );

    $result = makeHandler()->handle(new DeduplicateCorpus($corpus));
    expect($result->uniqueCount)->toBe(3);
    expect($result->clusters->count())->toBe(3);
    expect($result->duplicatesRemoved)->toBe(0);
});

it('handles_empty_corpus', function (): void {
    $result = makeHandler()->handle(new DeduplicateCorpus(CorpusSlice::empty()));
    expect($result->inputCount)->toBe(0);
    expect($result->uniqueCount)->toBe(0);
    expect($result->clusters->count())->toBe(0);
});

it('elects_representative_with_highest_completeness', function (): void {
    $bare = makeDeduplicatable('10.x/x', 'Work', abstract: null);
    $rich = makeDeduplicatable('10.x/x', 'Work', abstract: 'Great abstract');

    $corpus = CorpusSlice::fromWorks($bare, $rich);
    $result = makeHandler()->handle(new DeduplicateCorpus($corpus));

    $rep = $result->clusters->all()[0]->representative();
    expect($rep->hasAbstract())->toBeTrue();
});
