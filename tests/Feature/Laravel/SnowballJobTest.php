<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpus;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\CitationNetwork\Domain\Exception\UnknownSnowballingProviderAlias;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderPort;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobProgressed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\SnowballJob;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('is a queueable job that serializes only the snowballing payload', function (): void {
    $seed = snowballJobTestWork('10.4000/seed', 'Queueable Seed');
    $known = snowballJobTestWork('10.4000/known', 'Known Work');

    $job = new SnowballJob(new SnowballCorpus(
        projectId: 'project-a',
        seedCorpus: CorpusSlice::fromWorks($seed),
        knownCorpus: CorpusSlice::fromWorks($seed, $known),
        forward: true,
        backward: false,
        depth: 2,
        maxCitations: 25,
        maxReferences: 10,
        providerAliases: ['OpenAlex', 'semantic_scholar'],
    ));

    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(SnowballJob::class)
        ->and($restored)->toBeInstanceOf(ShouldQueue::class)
        ->and($restored->command->projectId)->toBe('project-a')
        ->and($restored->command->seedCorpus->count())->toBe(1)
        ->and($restored->command->knownCorpus?->count())->toBe(2)
        ->and($restored->command->forward)->toBeTrue()
        ->and($restored->command->backward)->toBeFalse()
        ->and($restored->command->depth)->toBe(2)
        ->and($restored->command->maxCitations)->toBe(25)
        ->and($restored->command->maxReferences)->toBe(10)
        ->and($restored->command->providerAliases)->toBe(['openalex', 'semantic_scholar'])
        ->and($restored->command->seedCorpus->all()[0]->primaryId()?->toString())->toBe('doi:10.4000/seed');
});

it('resolves the snowballing handler from the container when handling the job', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobProgressed::class, NexusJobCompleted::class, NexusJobFailed::class]);

    $seed = snowballJobTestWork('10.4000/seed', 'Seed');
    $new = snowballJobTestWork('10.4000/new', 'New Work');
    $provider = new SnowballJobTestProvider('openalex');
    $provider->forward[$seed->primaryId()->toString()] = [$new];

    app()->instance(SnowballCorpusHandler::class, new SnowballCorpusHandler(
        new SnowballingProviderCollection($provider),
        new SnowballJobTestDeduplication(),
    ));

    $job = new SnowballJob(new SnowballCorpus(
        projectId: 'project-from-job',
        seedCorpus: CorpusSlice::fromWorks($seed),
        forward: true,
        backward: false,
        depth: 1,
        maxCitations: 5,
        providerAliases: ['openalex'],
    ));

    $result = app()->call([$job, 'handle']);

    expect($result->projectId)->toBe('project-from-job')
        ->and($result->initialSeedCount)->toBe(1)
        ->and($result->depthReached)->toBe(1)
        ->and($result->newCorpus->count())->toBe(1)
        ->and($result->newCorpus->all()[0]->title())->toBe('New Work')
        ->and($provider->fetchedSeeds)->toBe(['doi:10.4000/seed']);

    Event::assertDispatched(
        NexusJobStarted::class,
        fn (NexusJobStarted $event): bool => $event->jobName === 'snowball_corpus'
            && $event->context['project_id'] === 'project-from-job'
            && $event->context['seed_count'] === 1
            && $event->context['known_count'] === 1
            && $event->context['forward'] === true
            && $event->context['backward'] === false
            && $event->context['provider_aliases'] === ['openalex']
    );
    Event::assertDispatched(
        NexusJobCompleted::class,
        fn (NexusJobCompleted $event): bool => $event->jobName === 'snowball_corpus'
            && $event->summary['initial_seed_count'] === 1
            && $event->summary['depth_reached'] === 1
            && $event->summary['round_count'] === 1
            && $event->summary['provider_stat_count'] === 1
            && $event->summary['provider_failure_count'] === 0
            && $event->summary['total_discovered'] === 1
            && $event->summary['total_net_new'] === 1
            && $event->summary['combined_count'] === 2
            && $event->summary['new_count'] === 1
            && is_int($event->durationMs)
    );
    Event::assertDispatchedTimes(NexusJobProgressed::class, 2);
    Event::assertDispatched(
        NexusJobProgressed::class,
        fn (NexusJobProgressed $event): bool => $event->jobName === 'snowball_corpus'
            && $event->progressKey === 'round:1'
            && $event->context['progress_type'] === 'snowball_round'
            && $event->context['depth'] === 1
            && $event->summary['discovered_count'] === 1
            && $event->summary['net_new_count'] === 1
    );
    Event::assertDispatched(
        NexusJobProgressed::class,
        fn (NexusJobProgressed $event): bool => str_starts_with($event->progressKey, 'provider:0:openalex:forward:')
            && $event->context['progress_type'] === 'snowball_provider'
            && $event->context['provider_alias'] === 'openalex'
            && $event->context['direction'] === 'forward'
            && $event->context['seed_work_id'] === 'doi:10.4000/seed'
            && $event->summary['result_count'] === 1
            && $event->summary['failed'] === false
    );
    Event::assertNotDispatched(NexusJobFailed::class);
});

it('dispatches a failed lifecycle event before rethrowing snowballing failures', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobProgressed::class, NexusJobCompleted::class, NexusJobFailed::class]);

    $seed = snowballJobTestWork('10.4000/seed', 'Seed');

    app()->instance(SnowballCorpusHandler::class, new SnowballCorpusHandler(
        new SnowballingProviderCollection(new SnowballJobTestProvider('semantic_scholar')),
        new SnowballJobTestDeduplication(),
    ));

    $job = new SnowballJob(new SnowballCorpus(
        projectId: 'project-from-job',
        seedCorpus: CorpusSlice::fromWorks($seed),
        providerAliases: ['openalex'],
    ));

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(UnknownSnowballingProviderAlias::class, 'Unknown snowballing provider alias: openalex');

    Event::assertDispatched(NexusJobStarted::class);
    Event::assertNotDispatched(NexusJobProgressed::class);
    Event::assertNotDispatched(NexusJobCompleted::class);
    Event::assertDispatched(
        NexusJobFailed::class,
        fn (NexusJobFailed $event): bool => $event->jobName === 'snowball_corpus'
            && $event->errorClass === UnknownSnowballingProviderAlias::class
            && str_contains($event->errorMessage, 'Unknown snowballing provider alias: openalex')
            && $event->context['project_id'] === 'project-from-job'
            && $event->context['provider_aliases'] === ['openalex']
    );
});

function snowballJobTestWork(string $doi, string $title): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: $title,
        sourceProvider: 'test',
    );
}

final class SnowballJobTestProvider implements SnowballingProviderPort
{
    /** @var array<string, list<ScholarlyWork>> */
    public array $forward = [];

    /** @var array<string, list<ScholarlyWork>> */
    public array $backward = [];

    /** @var list<string> */
    public array $fetchedSeeds = [];

    public function __construct(private readonly string $alias)
    {
    }

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

        return array_slice($this->forward[$seedId] ?? [], 0, $limit);
    }

    public function fetchReferencedWorks(ScholarlyWork $seed, int $limit): array
    {
        $seedId = $seed->primaryId()?->toString() ?? spl_object_hash($seed);
        $this->fetchedSeeds[] = $seedId;

        return array_slice($this->backward[$seedId] ?? [], 0, $limit);
    }
}

final class SnowballJobTestDeduplication implements DeduplicationPort
{
    public function deduplicate(CorpusSlice $corpus): CorpusSlice
    {
        return $corpus;
    }
}
