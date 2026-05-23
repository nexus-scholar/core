<?php

declare(strict_types=1);

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Plan\SearchPlan;
use Nexus\Search\Application\Plan\SearchPlanItem;
use Nexus\Search\Application\Plan\SearchPlanRunner;
use Nexus\Search\Application\Plan\SearchPlanRunOptions;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\UseCase\SearchAcrossProviders;
use Nexus\Shared\Domain\CorpusSlice;

it('runs selected plan items through the reusable executor with overrides', function (): void {
    $calls = [];
    $executor = new class($calls) implements SearchExecutorPort
    {
        public function __construct(private array &$calls) {}

        public function handle(SearchAcrossProviders $command): AggregatedResult
        {
            $this->calls[] = $command->query;

            return new AggregatedResult(CorpusSlice::empty(), [], 0);
        }
    };

    $plan = new SearchPlan('project-a', [
        new SearchPlanItem('Q1', 'Q1', 'first query', 'project-a', priority: 'high'),
        new SearchPlanItem('Q2', 'Q2', 'second query', 'project-a', priority: 'low'),
    ], 'inline');

    $result = (new SearchPlanRunner($executor))->run($plan, new SearchPlanRunOptions(
        onlyIds: ['Q1'],
        projectId: 'project-b',
        maxResults: 5,
        providerAliases: ['openalex'],
    ));

    expect($result->itemResults)->toHaveCount(1)
        ->and($calls)->toHaveCount(1)
        ->and($calls[0]->projectId)->toBe('project-b')
        ->and($calls[0]->maxResults)->toBe(5)
        ->and($calls[0]->providerAliases)->toBe(['openalex']);
});

it('can continue after an item failure and report the failed item', function (): void {
    $executor = new class implements SearchExecutorPort
    {
        public function handle(SearchAcrossProviders $command): AggregatedResult
        {
            if ($command->query->term->value === 'bad query') {
                throw new RuntimeException('provider exploded');
            }

            return new AggregatedResult(CorpusSlice::empty(), [], 0);
        }
    };

    $plan = new SearchPlan('project-a', [
        new SearchPlanItem('Q1', 'Q1', 'good query', 'project-a'),
        new SearchPlanItem('Q2', 'Q2', 'bad query', 'project-a'),
        new SearchPlanItem('Q3', 'Q3', 'another good query', 'project-a'),
    ]);

    $result = (new SearchPlanRunner($executor))->run($plan, new SearchPlanRunOptions(continueOnFailure: true));

    expect($result->itemResults)->toHaveCount(3)
        ->and($result->successCount())->toBe(2)
        ->and($result->failureCount())->toBe(1)
        ->and($result->itemResults[1]->error?->getMessage())->toBe('provider exploded');
});

it('stops on first failure when configured to fail fast', function (): void {
    $executor = new class implements SearchExecutorPort
    {
        public function handle(SearchAcrossProviders $command): AggregatedResult
        {
            throw new RuntimeException('stop now');
        }
    };

    $plan = new SearchPlan('project-a', [
        new SearchPlanItem('Q1', 'Q1', 'bad query', 'project-a'),
    ]);

    expect(fn () => (new SearchPlanRunner($executor))->run($plan, new SearchPlanRunOptions(continueOnFailure: false)))
        ->toThrow(RuntimeException::class, 'stop now');
});
