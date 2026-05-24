<?php

declare(strict_types=1);

use Nexus\Deduplication\Application\DeduplicateCorpus;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterCollection;
use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Deduplication\Infrastructure\CompletenessElectionPolicy;
use Nexus\Deduplication\Infrastructure\DoiMatchPolicy;
use Nexus\Deduplication\Infrastructure\FingerprintPolicy;
use Nexus\Deduplication\Infrastructure\NamespaceMatchPolicy;
use Nexus\Deduplication\Infrastructure\TitleFuzzyPolicy;
use Nexus\Deduplication\Infrastructure\TitleNormalizer;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
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
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: $title,
        sourceProvider: $provider,
        year: $year,
        abstract: $abstract,
    );
}

function makeHandler(array $extraPolicies = [], ?CorpusLockPolicy $lockPolicy = null): DeduplicateCorpusHandler
{
    $normalizer = new TitleNormalizer;

    return new DeduplicateCorpusHandler(
        policies: array_merge([
            new DoiMatchPolicy,
            new NamespaceMatchPolicy(WorkIdNamespace::ARXIV),
            new TitleFuzzyPolicy($normalizer),
            new FingerprintPolicy($normalizer),
        ], $extraPolicies),
        electionPolicy: new CompletenessElectionPolicy,
        lockPolicy: $lockPolicy,
    );
}

// ── DoiMatchPolicy ────────────────────────────────────────────────────────────

it('detects_two_works_with_identical_doi', function (): void {
    $a = makeDeduplicatable('10.1234/abc');
    $b = makeDeduplicatable('10.1234/abc');

    $policy = new DoiMatchPolicy;
    $dupes = $policy->detect([$a, $b]);

    expect($dupes)->toHaveCount(1);
    expect($dupes[0]->reason->value)->toBe('doi_match');
    expect($dupes[0]->confidence)->toBe(1.0);
});

it('normalizes_doi_before_comparing', function (): void {
    // Both should normalize to the same DOI value
    $a = makeDeduplicatable('https://doi.org/10.1234/abc');
    $b = makeDeduplicatable('doi:10.1234/abc');

    $policy = new DoiMatchPolicy;
    $dupes = $policy->detect([$a, $b]);

    expect($dupes)->toHaveCount(1);
});

it('ignores_works_without_doi', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.12345')]),
        title: 'No DOI',
        sourceProvider: 'arxiv',
    );

    $policy = new DoiMatchPolicy;
    expect($policy->detect([$work]))->toBe([]);
});

it('returns_empty_when_all_dois_are_unique', function (): void {
    $works = [
        makeDeduplicatable('10.1234/aaa'),
        makeDeduplicatable('10.1234/bbb'),
        makeDeduplicatable('10.1234/ccc'),
    ];

    $policy = new DoiMatchPolicy;
    expect($policy->detect($works))->toBe([]);
});

// ── DedupCluster ──────────────────────────────────────────────────────────────

it('starts_with_single_seed_as_representative', function (): void {
    $seed = makeDeduplicatable('10.x/seed');
    $cluster = DedupCluster::startWith($seed, 'test-project');

    expect($cluster->size())->toBe(1);
    expect($cluster->representative()->primaryId()?->toString())
        ->toBe($seed->primaryId()->toString());
});

it('absorbs_a_duplicate_work', function (): void {
    $seed = makeDeduplicatable('10.x/seed');
    $other = makeDeduplicatable('10.x/other');

    $cluster = DedupCluster::startWith($seed, 'test-project');
    $evidence = new Duplicate(
        primaryId: $seed->primaryId(),
        secondaryId: $other->primaryId(),
        reason: DuplicateReason::DOI_MATCH,
        confidence: 1.0,
    );

    $cluster->absorb($other, $evidence);

    expect($cluster->size())->toBe(2);
});

it('size_grows_on_absorb', function (): void {
    $seed = makeDeduplicatable('10.x/a');
    $cluster = DedupCluster::startWith($seed, 'test-project');

    for ($i = 1; $i <= 3; $i++) {
        $work = makeDeduplicatable("10.x/b{$i}");
        $ev = new Duplicate(
            primaryId: $seed->primaryId(),
            secondaryId: $work->primaryId(),
            reason: DuplicateReason::DOI_MATCH,
            confidence: 1.0,
        );
        $cluster->absorb($work, $ev);
    }

    expect($cluster->size())->toBe(4);
});

it('collects_all_dois_from_all_members', function (): void {
    $seed = makeDeduplicatable('10.x/a');
    $b = makeDeduplicatable('10.x/b');
    $cluster = DedupCluster::startWith($seed, 'test-project');
    $ev = new Duplicate(
        primaryId: $seed->primaryId(),
        secondaryId: $b->primaryId(),
        reason: DuplicateReason::DOI_MATCH,
        confidence: 1.0,
    );
    $cluster->absorb($b, $ev);

    expect($cluster->allDois())->toContain('10.x/a');
    expect($cluster->allDois())->toContain('10.x/b');
});

it('elects_most_complete_work_as_representative', function (): void {
    $bare = makeDeduplicatable('10.x/bare', abstract: null);
    $rich = makeDeduplicatable('10.x/rich', abstract: 'Has abstract');

    $cluster = DedupCluster::startWith($bare, 'test-project');
    $ev = new Duplicate(
        primaryId: $bare->primaryId(),
        secondaryId: $rich->primaryId(),
        reason: DuplicateReason::DOI_MATCH,
        confidence: 1.0,
    );
    $cluster->absorb($rich, $ev);
    $cluster->electRepresentative(new CompletenessElectionPolicy);

    expect($cluster->representative()->hasAbstract())->toBeTrue();
});

it('fills missing representative fields from duplicate members in exported corpus', function (): void {
    $openAlex = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::OPENALEX, 'https://openalex.org/W1'),
            new WorkId(WorkIdNamespace::ARXIV, '2301.00001'),
        ]),
        title: 'Universal ultrasound foundation model',
        sourceProvider: 'openalex',
        year: 2024,
        abstract: null,
    );

    $arxiv = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, '2301.00001')]),
        title: 'Universal ultrasound foundation model',
        sourceProvider: 'arxiv',
        year: 2024,
        abstract: 'Abstract from arXiv.',
    );

    $cluster = DedupCluster::startWith($openAlex, 'test-project');
    $cluster->absorb($arxiv, new Duplicate(
        primaryId: $openAlex->primaryId(),
        secondaryId: $arxiv->primaryId(),
        reason: DuplicateReason::ARXIV_MATCH,
        confidence: 1.0,
    ));
    $cluster->electRepresentative(new CompletenessElectionPolicy);

    expect($cluster->representative()->sourceProvider())->toBe('openalex');
    expect($cluster->representative()->abstract())->toBeNull();

    $corpus = (new DedupClusterCollection($cluster))->toCorpusSlice();

    expect($corpus->all()[0]->sourceProvider())->toBe('openalex');
    expect($corpus->all()[0]->abstract())->toBe('Abstract from arXiv.');
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

it('blocks deduplication when the project corpus is locked', function (): void {
    $policy = new CorpusLockPolicy(
        new DedupTestLocks(['project-1' => true]),
        new DedupTestMembership,
    );

    expect(fn () => makeHandler(lockPolicy: $policy)->handle(new DeduplicateCorpus(
        CorpusSlice::fromWorks(makeDeduplicatable('10.1234/locked')),
        projectId: 'project-1',
    )))->toThrow(ProjectLockedException::class);
});

it('clusters_transitively_via_union_find', function (): void {
    // A shares DOI with B; B shares arXiv with C → all three in one cluster
    $arxivId = '2301.99999';
    $a = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.x/abc')]),
        title: 'Work A', sourceProvider: 'openalex',
    );
    $b = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::DOI, '10.x/abc'),
            new WorkId(WorkIdNamespace::ARXIV, $arxivId),
        ]),
        title: 'Work B', sourceProvider: 'crossref',
    );
    $c = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::ARXIV, $arxivId)]),
        title: 'Work C', sourceProvider: 'arxiv',
    );

    $corpus = CorpusSlice::fromWorks($a, $b, $c);
    $result = makeHandler()->handle(new DeduplicateCorpus($corpus));

    expect($result->uniqueCount)->toBeLessThan(3);
});

it('preserves direct evidence for transitively absorbed cluster members', function (): void {
    $a = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::OPENALEX, 'W-A'),
            new WorkId(WorkIdNamespace::ARXIV, '2301.11111'),
        ]),
        title: 'Alpha title',
        sourceProvider: 'openalex',
    );
    $b = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::OPENALEX, 'W-B'),
            new WorkId(WorkIdNamespace::ARXIV, '2301.11111'),
            new WorkId(WorkIdNamespace::S2, 'S2-CHAIN'),
        ]),
        title: 'Bridge title',
        sourceProvider: 'semantic_scholar',
    );
    $c = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([
            new WorkId(WorkIdNamespace::OPENALEX, 'W-C'),
            new WorkId(WorkIdNamespace::S2, 'S2-CHAIN'),
        ]),
        title: 'Gamma title',
        sourceProvider: 'semantic_scholar',
    );

    $result = makeHandler([new NamespaceMatchPolicy(WorkIdNamespace::S2)])
        ->handle(new DeduplicateCorpus(CorpusSlice::fromWorksUnsafe($a, $b, $c)));

    $cluster = $result->clusters->all()[0];
    $reasons = array_map(
        fn (Duplicate $evidence): DuplicateReason => $evidence->reason,
        $cluster->duplicateEvidence(),
    );

    expect($cluster->size())->toBe(3);
    expect($reasons)->toContain(DuplicateReason::ARXIV_MATCH);
    expect($reasons)->toContain(DuplicateReason::S2_MATCH);
    expect($reasons)->not->toContain(DuplicateReason::FINGERPRINT);
});

it('runs each deduplication policy once while assembling clusters', function (): void {
    $a = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::OPENALEX, 'W-COUNT-A')]),
        title: 'Count A',
        sourceProvider: 'fixture',
    );
    $b = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::OPENALEX, 'W-COUNT-B')]),
        title: 'Count B',
        sourceProvider: 'fixture',
    );
    $policy = new CountingDeduplicationPolicy([
        new Duplicate(
            primaryId: $a->primaryId(),
            secondaryId: $b->primaryId(),
            reason: DuplicateReason::OPENALEX_MATCH,
            confidence: 1.0,
        ),
    ]);
    $handler = new DeduplicateCorpusHandler(
        policies: [$policy],
        electionPolicy: new CompletenessElectionPolicy,
    );

    $result = $handler->handle(new DeduplicateCorpus(CorpusSlice::fromWorksUnsafe($a, $b)));

    expect($result->uniqueCount)->toBe(1);
    expect($policy->detectCalls)->toBe(1);
});

final class DedupTestLocks implements ProjectLockPort
{
    /**
     * @param  array<string, bool>  $locks
     */
    public function __construct(private readonly array $locks) {}

    public function isLocked(string $projectId): bool
    {
        return $this->locks[$projectId] ?? false;
    }
}

final class DedupTestMembership implements ProjectWorkMembershipPort
{
    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return [];
    }
}

final class CountingDeduplicationPolicy implements DeduplicationPolicyPort
{
    public int $detectCalls = 0;

    /**
     * @param  Duplicate[]  $duplicates
     */
    public function __construct(private readonly array $duplicates) {}

    public function name(): string
    {
        return 'counting';
    }

    public function detect(array $works): array
    {
        $this->detectCalls++;

        return $this->duplicates;
    }
}

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
