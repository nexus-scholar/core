<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Application\UseCase\SnowballCorpus;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\CitationNetwork\Domain\Exception\UnknownSnowballingProviderAlias;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderPort;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function snowballHandlerTestWork(string $doi, string $title): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: $title,
        sourceProvider: 'test',
    );
}

it('runs a forward snowballing round and separates already-known from net-new works', function (): void {
    $seed = snowballHandlerTestWork('10.3000/seed', 'Seed');
    $known = snowballHandlerTestWork('10.3000/known', 'Known');
    $new = snowballHandlerTestWork('10.3000/new', 'New');
    $provider = new SnowballHandlerTestProvider('semantic_scholar');
    $provider->forward[$seed->primaryId()->toString()] = [$known, $new];
    $deduplication = new SnowballHandlerTestDeduplication;

    $result = (new SnowballCorpusHandler(
        new SnowballingProviderCollection($provider),
        $deduplication,
    ))->handle(new SnowballCorpus(
        projectId: 'project-1',
        seedCorpus: CorpusSlice::fromWorks($seed),
        knownCorpus: CorpusSlice::fromWorks($seed, $known),
        forward: true,
        backward: false,
        depth: 1,
        providerAliases: ['semantic_scholar'],
    ));

    expect($result->depthReached)->toBe(1)
        ->and($result->newCorpus->count())->toBe(1)
        ->and($result->newCorpus->findById($new->primaryId()))->toBe($new)
        ->and($result->combinedCorpus->count())->toBe(3)
        ->and($result->rounds)->toHaveCount(1)
        ->and($result->rounds[0]->depth)->toBe(1)
        ->and($result->rounds[0]->inputSeedCount)->toBe(1)
        ->and($result->rounds[0]->discoveredCount)->toBe(2)
        ->and($result->rounds[0]->deduplicatedDiscoveredCount)->toBe(2)
        ->and($result->rounds[0]->alreadyKnownCount)->toBe(1)
        ->and($result->rounds[0]->netNewCount)->toBe(1)
        ->and($result->rounds[0]->providerFailureCount)->toBe(0)
        ->and($result->providerStats)->toHaveCount(1)
        ->and($result->providerStats[0]->alias)->toBe('semantic_scholar')
        ->and($result->providerStats[0]->direction)->toBe(SnowballDirection::FORWARD)
        ->and($result->providerStats[0]->resultCount)->toBe(2)
        ->and($deduplication->inputCounts)->toBe([2, 3]);
});

it('uses net-new works as the next depth seeds', function (): void {
    $seed = snowballHandlerTestWork('10.3000/seed', 'Seed');
    $first = snowballHandlerTestWork('10.3000/first', 'First');
    $second = snowballHandlerTestWork('10.3000/second', 'Second');
    $provider = new SnowballHandlerTestProvider('semantic_scholar');
    $provider->forward[$seed->primaryId()->toString()] = [$first];
    $provider->forward[$first->primaryId()->toString()] = [$second];

    $result = (new SnowballCorpusHandler(
        new SnowballingProviderCollection($provider),
        new SnowballHandlerTestDeduplication,
    ))->handle(new SnowballCorpus(
        projectId: 'project-1',
        seedCorpus: CorpusSlice::fromWorks($seed),
        forward: true,
        backward: false,
        depth: 2,
    ));

    expect($result->depthReached)->toBe(2)
        ->and($result->newCorpus->count())->toBe(2)
        ->and($result->newCorpus->findById($first->primaryId()))->toBe($first)
        ->and($result->newCorpus->findById($second->primaryId()))->toBe($second)
        ->and($result->rounds[0]->netNewCount)->toBe(1)
        ->and($result->rounds[1]->inputSeedCount)->toBe(1)
        ->and($result->rounds[1]->netNewCount)->toBe(1)
        ->and($provider->fetchedSeeds)->toBe([
            $seed->primaryId()->toString(),
            $first->primaryId()->toString(),
        ]);
});

it('records provider failures without discarding other provider results', function (): void {
    $seed = snowballHandlerTestWork('10.3000/seed', 'Seed');
    $new = snowballHandlerTestWork('10.3000/new', 'New');
    $failingProvider = new SnowballHandlerTestProvider('failing');
    $failingProvider->failForward = true;
    $workingProvider = new SnowballHandlerTestProvider('working');
    $workingProvider->forward[$seed->primaryId()->toString()] = [$new];

    $result = (new SnowballCorpusHandler(
        new SnowballingProviderCollection($failingProvider, $workingProvider),
        new SnowballHandlerTestDeduplication,
    ))->handle(new SnowballCorpus(
        projectId: 'project-1',
        seedCorpus: CorpusSlice::fromWorks($seed),
        forward: true,
        backward: false,
    ));

    expect($result->newCorpus->count())->toBe(1)
        ->and($result->rounds[0]->providerFailureCount)->toBe(1)
        ->and($result->providerStats)->toHaveCount(2)
        ->and($result->providerStats[0]->failed())->toBeTrue()
        ->and($result->providerStats[0]->errorMessage)->toBe('provider unavailable')
        ->and($result->providerStats[1]->failed())->toBeFalse();
});

it('fails clearly when a selected snowballing provider alias is unknown', function (): void {
    $seed = snowballHandlerTestWork('10.3000/seed', 'Seed');

    expect(fn () => (new SnowballCorpusHandler(
        new SnowballingProviderCollection(new SnowballHandlerTestProvider('semantic_scholar')),
        new SnowballHandlerTestDeduplication,
    ))->handle(new SnowballCorpus(
        projectId: 'project-1',
        seedCorpus: CorpusSlice::fromWorks($seed),
        providerAliases: ['openalex'],
    )))->toThrow(UnknownSnowballingProviderAlias::class, 'Unknown snowballing provider alias: openalex');
});

it('blocks snowballing when the project corpus is locked', function (): void {
    $seed = snowballHandlerTestWork('10.3000/seed', 'Seed');

    expect(fn () => (new SnowballCorpusHandler(
        new SnowballingProviderCollection(new SnowballHandlerTestProvider('semantic_scholar')),
        new SnowballHandlerTestDeduplication,
        new CorpusLockPolicy(
            new SnowballTestLocks(['project-1' => true]),
            new SnowballTestMembership,
        ),
    ))->handle(new SnowballCorpus(
        projectId: 'project-1',
        seedCorpus: CorpusSlice::fromWorks($seed),
    )))->toThrow(ProjectLockedException::class);
});

final class SnowballHandlerTestProvider implements SnowballingProviderPort
{
    /** @var array<string, list<ScholarlyWork>> */
    public array $forward = [];

    /** @var array<string, list<ScholarlyWork>> */
    public array $backward = [];

    /** @var list<string> */
    public array $fetchedSeeds = [];

    public bool $failForward = false;

    public bool $failBackward = false;

    public function __construct(private readonly string $alias) {}

    public function alias(): string
    {
        return $this->alias;
    }

    public function supportsSnowballing(ScholarlyWork $seed, SnowballDirection $direction): bool
    {
        return $seed->primaryId() !== null;
    }

    public function fetchCitingWorks(ScholarlyWork $seed, int $limit): array
    {
        $seedId = $seed->primaryId()?->toString() ?? spl_object_hash($seed);
        $this->fetchedSeeds[] = $seedId;

        if ($this->failForward) {
            throw new RuntimeException('provider unavailable');
        }

        return array_slice($this->forward[$seedId] ?? [], 0, $limit);
    }

    public function fetchReferencedWorks(ScholarlyWork $seed, int $limit): array
    {
        $seedId = $seed->primaryId()?->toString() ?? spl_object_hash($seed);

        if ($this->failBackward) {
            throw new RuntimeException('provider unavailable');
        }

        return array_slice($this->backward[$seedId] ?? [], 0, $limit);
    }
}

final class SnowballHandlerTestDeduplication implements DeduplicationPort
{
    /** @var list<int> */
    public array $inputCounts = [];

    public function deduplicate(CorpusSlice $corpus): CorpusSlice
    {
        $this->inputCounts[] = $corpus->count();

        return $corpus;
    }
}

final class SnowballTestLocks implements ProjectLockPort
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

final class SnowballTestMembership implements ProjectWorkMembershipPort
{
    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return [];
    }
}
