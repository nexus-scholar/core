<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\SearchJob;
use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Plan\SearchPlan;
use Nexus\Search\Application\Plan\SearchPlanItem;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Search\Domain\CorpusSlice;

it('is a queueable job that serializes only the search plan payload', function (): void {
    $job = new SearchJob(
        new SearchPlan('project-a', [
            new SearchPlanItem(
                id: 'Q1',
                label: 'Query 1',
                query: 'graph neural networks',
                projectId: 'project-a',
                maxResults: 25,
                providerAliases: ['openalex'],
                priority: 'high',
            ),
        ], 'queries.yml'),
        new SearchPlanRunOptions(
            onlyIds: ['Q1'],
            projectId: 'project-b',
            maxResults: 5,
            providerAliases: ['semantic_scholar'],
        ),
    );

    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(SearchJob::class)
        ->and($restored)->toBeInstanceOf(ShouldQueue::class)
        ->and($restored->plan->sourceName)->toBe('queries.yml')
        ->and($restored->plan->items[0]->query)->toBe('graph neural networks')
        ->and($restored->options?->onlyIds)->toBe(['Q1'])
        ->and($restored->options?->projectId)->toBe('project-b')
        ->and($restored->options?->providerAliases)->toBe(['semantic_scholar']);
});

it('resolves the search runner from the container when handling the job', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobCompleted::class, NexusJobFailed::class]);

    $received = (object) ['command' => null];

    app()->instance(SearchExecutorPort::class, new class($received) implements SearchExecutorPort {
        public function __construct(private readonly object $received) {}

        public function handle(SearchAcrossProviders $command): AggregatedResult
        {
            $this->received->command = $command;

            return new AggregatedResult(CorpusSlice::empty(), [], 0);
        }
    });

    $job = new SearchJob(
        new SearchPlan('project-a', [
            new SearchPlanItem(
                id: 'Q1',
                label: 'Query 1',
                query: 'citation networks',
                projectId: 'project-a',
                maxResults: 20,
                providerAliases: ['openalex'],
            ),
        ]),
        new SearchPlanRunOptions(
            projectId: 'project-override',
            maxResults: 7,
            providerAliases: ['semantic_scholar'],
        ),
    );

    $result = app()->call([$job, 'handle']);

    expect($result->successCount())->toBe(1)
        ->and($received->command)->toBeInstanceOf(SearchAcrossProviders::class)
        ->and($received->command->query->term->value)->toBe('citation networks')
        ->and($received->command->query->projectId)->toBe('project-override')
        ->and($received->command->query->maxResults)->toBe(7)
        ->and($received->command->query->providerAliases)->toBe(['semantic_scholar']);

    Event::assertDispatched(
        NexusJobStarted::class,
        fn (NexusJobStarted $event): bool => $event->jobName === 'search'
            && $event->context['project_id'] === 'project-override'
            && $event->context['query_count'] === 1
    );
    Event::assertDispatched(
        NexusJobCompleted::class,
        fn (NexusJobCompleted $event): bool => $event->jobName === 'search'
            && $event->summary['success_count'] === 1
            && $event->summary['failure_count'] === 0
            && is_int($event->durationMs)
    );
    Event::assertNotDispatched(NexusJobFailed::class);
});

it('dispatches a failed lifecycle event before rethrowing search job failures', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobCompleted::class, NexusJobFailed::class]);

    app()->instance(SearchExecutorPort::class, new class implements SearchExecutorPort {
        public function handle(SearchAcrossProviders $command): AggregatedResult
        {
            throw new RuntimeException('search failed');
        }
    });

    $job = new SearchJob(
        new SearchPlan('project-a', [
            new SearchPlanItem('Q1', 'Query 1', 'bad query', 'project-a'),
        ]),
        new SearchPlanRunOptions(continueOnFailure: false),
    );

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(RuntimeException::class, 'search failed');

    Event::assertDispatched(NexusJobStarted::class);
    Event::assertNotDispatched(NexusJobCompleted::class);
    Event::assertDispatched(
        NexusJobFailed::class,
        fn (NexusJobFailed $event): bool => $event->jobName === 'search'
            && $event->errorClass === RuntimeException::class
            && $event->errorMessage === 'search failed'
            && $event->context['project_id'] === 'project-a'
    );
});
