<?php

declare(strict_types=1);

namespace Tests\Unit\Deduplication\Infrastructure;

use Nexus\Deduplication\Infrastructure\CompletenessElectionPolicy;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('elects_work_with_highest_completeness_and_priority_score', function (): void {
    $policy = new CompletenessElectionPolicy([
        'provider_a' => 10,
        'provider_b' => 5,
    ]);

    // Lower completeness but higher priority (10)
    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Title A',
        sourceProvider: 'provider_a',
        year: 2020 // Completeness +1
    ); // Total: 11

    // Higher completeness (+2) but lower priority (5)
    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Title A',
        sourceProvider: 'provider_b',
        year: 2020,
        abstract: 'Abstract here' // Completeness +2
    ); // Total: 7

    $winner = $policy->elect([$workB, $workA]);
    expect($winner->sourceProvider())->toBe('provider_a');
});

it('uses_doi_presence_as_first_tie_breaker', function (): void {
    $policy = new CompletenessElectionPolicy(['provider_a' => 5, 'provider_b' => 5]);

    // No DOI
    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::ARXIV, '1234')),
        title: 'Title',
        sourceProvider: 'provider_a'
    );

    // Has DOI
    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/b')),
        title: 'Title',
        sourceProvider: 'provider_b'
    );

    $winner = $policy->elect([$workA, $workB]);
    expect($winner->sourceProvider())->toBe('provider_b');
});

it('uses_earlier_retrieval_time_as_second_tie_breaker', function (): void {
    $policy = new CompletenessElectionPolicy(['provider_a' => 5, 'provider_b' => 5]);

    $workA = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Title',
        sourceProvider: 'provider_a'
    );

    usleep(10000); // 10ms delay

    $workB = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1000/a')),
        title: 'Title',
        sourceProvider: 'provider_b'
    );

    $winner = $policy->elect([$workB, $workA]); // pass B first to ensure sort works
    expect($winner->sourceProvider())->toBe('provider_a');
});

it('throws_exception_on_empty_member_list', function (): void {
    $policy = new CompletenessElectionPolicy();
    expect(fn() => $policy->elect([]))->toThrow(\InvalidArgumentException::class);
});
