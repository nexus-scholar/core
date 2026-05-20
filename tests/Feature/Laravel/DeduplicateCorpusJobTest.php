<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Nexus\Deduplication\Application\DeduplicateCorpusHandler;
use Nexus\Deduplication\Domain\Duplicate;
use Nexus\Deduplication\Domain\DuplicateReason;
use Nexus\Deduplication\Domain\Port\DeduplicationPolicyPort;
use Nexus\Deduplication\Domain\Port\RepresentativeElectionPort;
use Nexus\Laravel\Event\NexusJobCompleted;
use Nexus\Laravel\Event\NexusJobFailed;
use Nexus\Laravel\Event\NexusJobStarted;
use Nexus\Laravel\Job\DeduplicateCorpusJob;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('is a queueable job that serializes only the deduplication payload', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/job')]),
        title: 'Queueable Deduplication',
        sourceProvider: 'openalex',
    );

    $job = new DeduplicateCorpusJob(
        corpus: CorpusSlice::fromWorks($work),
        projectId: 'project-a',
        policyAliases: ['doi_match'],
    );

    $restored = unserialize(serialize($job));

    expect($restored)->toBeInstanceOf(DeduplicateCorpusJob::class)
        ->and($restored)->toBeInstanceOf(ShouldQueue::class)
        ->and($restored->projectId)->toBe('project-a')
        ->and($restored->policyAliases)->toBe(['doi_match'])
        ->and($restored->corpus->count())->toBe(1)
        ->and($restored->corpus->all()[0]->title())->toBe('Queueable Deduplication')
        ->and($restored->corpus->all()[0]->primaryId()?->toString())->toBe('doi:10.1234/job');
});

it('resolves the deduplication handler from the container when handling the job', function (): void {
    Event::fake([NexusJobStarted::class, NexusJobCompleted::class, NexusJobFailed::class]);

    $received = (object) ['works' => null];

    $policy = new class($received) implements DeduplicationPolicyPort {
        public function __construct(private readonly object $received) {}

        public function name(): string
        {
            return 'job_policy';
        }

        public function detect(array $works): array
        {
            $this->received->works = $works;

            if (count($works) < 2) {
                return [];
            }

            return [
                new Duplicate(
                    primaryId: $works[0]->primaryId(),
                    secondaryId: $works[1]->primaryId(),
                    reason: DuplicateReason::DOI_MATCH,
                    confidence: 1.0,
                ),
            ];
        }
    };

    $election = new class implements RepresentativeElectionPort {
        public function elect(array $members): ScholarlyWork
        {
            return $members[0];
        }
    };

    app()->instance(DeduplicateCorpusHandler::class, new DeduplicateCorpusHandler(
        policies: [$policy],
        electionPolicy: $election,
    ));

    $workA = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/a')]),
        title: 'Container Resolved Work A',
        sourceProvider: 'openalex',
    );
    $workB = ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.1234/b')]),
        title: 'Container Resolved Work B',
        sourceProvider: 'crossref',
    );

    $job = new DeduplicateCorpusJob(
        corpus: CorpusSlice::fromWorksUnsafe($workA, $workB),
        projectId: 'project-from-job',
        policyAliases: ['job_policy'],
    );

    $result = app()->call([$job, 'handle']);

    expect($result->inputCount)->toBe(2)
        ->and($result->uniqueCount)->toBe(1)
        ->and($result->duplicatesRemoved)->toBe(1)
        ->and($result->policyStats)->toBe(['job_policy' => 1])
        ->and($result->clusters->all()[0]->projectId)->toBe('project-from-job')
        ->and($received->works)->toHaveCount(2)
        ->and($received->works[0]->title())->toBe('Container Resolved Work A');

    Event::assertDispatched(
        NexusJobStarted::class,
        fn (NexusJobStarted $event): bool => $event->jobName === 'deduplicate_corpus'
            && $event->context['project_id'] === 'project-from-job'
            && $event->context['input_count'] === 2
            && $event->context['policy_aliases'] === ['job_policy']
    );
    Event::assertDispatched(
        NexusJobCompleted::class,
        fn (NexusJobCompleted $event): bool => $event->jobName === 'deduplicate_corpus'
            && $event->summary['input_count'] === 2
            && $event->summary['unique_count'] === 1
            && $event->summary['duplicates_removed'] === 1
            && $event->summary['policy_stats'] === ['job_policy' => 1]
    );
    Event::assertNotDispatched(NexusJobFailed::class);
});
