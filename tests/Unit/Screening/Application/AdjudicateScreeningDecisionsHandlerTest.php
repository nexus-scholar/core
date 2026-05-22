<?php

declare(strict_types=1);

use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsCommand;
use Nexus\Screening\Application\UseCase\AdjudicateScreeningDecisionsHandler;
use Nexus\Screening\Application\UseCase\HumanAdjudicationInput;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\Exception\ProjectNotLockedException;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;

it('records auditable human adjudication decisions without overwriting prior decisions', function (): void {
    $runs = new AdjudicationTestRuns;
    $decisions = new AdjudicationTestDecisions([
        new ScreeningVerdict(
            id: 'llm-decision-1',
            screeningRunId: 'llm-run',
            projectId: 'project-1',
            workId: 'work-1',
            stage: ScreeningStage::TITLE_ABSTRACT,
            decision: ScreeningDecision::EXCLUDE,
            confidence: 0.84,
            source: 'llm_council',
            rationale: new ScreeningRationale('Council excluded.'),
            decidedBy: 'council',
            decidedAt: new DateTimeImmutable('2026-05-22T10:00:00+00:00'),
            criteriaHash: 'criteria-hash',
        ),
    ]);

    $handler = new AdjudicateScreeningDecisionsHandler(
        $runs,
        $decisions,
        adjudicationPolicy(locked: true),
    );

    $result = $handler->handle(new AdjudicateScreeningDecisionsCommand(
        projectId: 'project-1',
        actorId: 'reviewer-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        criteriaHash: 'criteria-hash',
        decisions: [
            new HumanAdjudicationInput(
                workId: 'work-1',
                decision: ScreeningDecision::INCLUDE,
                reason: 'The abstract directly studies tomato instance segmentation with limited labels.',
                evidence: ['tomato instance segmentation', 'limited labels'],
                sourceDecisionIds: ['llm-decision-1'],
            ),
        ],
        screeningRunId: 'human-run-1',
        runName: 'Reviewer adjudication',
    ));

    $latest = $decisions->latestForWork('project-1', 'work-1', ScreeningStage::TITLE_ABSTRACT);

    expect($result->runId)->toBe('human-run-1')
        ->and($result->included)->toBe(1)
        ->and($runs->started['human-run-1']->mode)->toBe(ScreeningRunMode::HUMAN)
        ->and($runs->started['human-run-1']->resolvedCriteriaHash())->toBe('criteria-hash')
        ->and($runs->completed['human-run-1']['included'])->toBe(1)
        ->and($decisions->recorded)->toHaveCount(2)
        ->and($latest?->source)->toBe('human')
        ->and($latest?->decision)->toBe(ScreeningDecision::INCLUDE)
        ->and($latest?->metadata['source_decision_ids'])->toBe(['llm-decision-1']);
});

it('requires a locked project before human adjudication', function (): void {
    $handler = new AdjudicateScreeningDecisionsHandler(
        new AdjudicationTestRuns,
        new AdjudicationTestDecisions,
        adjudicationPolicy(locked: false),
    );

    expect(fn () => $handler->handle(new AdjudicateScreeningDecisionsCommand(
        projectId: 'project-1',
        actorId: 'reviewer-1',
        stage: ScreeningStage::TITLE_ABSTRACT,
        criteriaHash: 'criteria-hash',
        decisions: [
            new HumanAdjudicationInput(
                workId: 'work-1',
                decision: ScreeningDecision::NEEDS_REVIEW,
                reason: 'Needs a second reviewer.',
            ),
        ],
    )))->toThrow(ProjectNotLockedException::class);
});

it('requires human rationale for every adjudicated decision', function (): void {
    expect(fn () => new HumanAdjudicationInput(
        workId: 'work-1',
        decision: ScreeningDecision::EXCLUDE,
        reason: '',
    ))->toThrow(InvalidArgumentException::class, 'requires a rationale');
});

function adjudicationPolicy(bool $locked): CorpusLockPolicy
{
    return new CorpusLockPolicy(
        new AdjudicationTestLocks(['project-1' => $locked]),
        new AdjudicationTestMembership,
    );
}

final class AdjudicationTestLocks implements ProjectLockPort
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

final class AdjudicationTestMembership implements ProjectWorkMembershipPort
{
    public function missingWorkIds(string $projectId, array $workIds): array
    {
        return [];
    }
}

final class AdjudicationTestRuns implements ScreeningRunRepositoryPort
{
    /** @var array<string, ScreeningRun> */
    public array $started = [];

    /** @var array<string, array<string, int>> */
    public array $completed = [];

    public function get(string $screeningRunId): ?ScreeningRun
    {
        return $this->started[$screeningRunId] ?? null;
    }

    public function start(ScreeningRun $run): void
    {
        $this->started[$run->id] = $run;
    }

    public function complete(string $screeningRunId, array $counts): void
    {
        $this->completed[$screeningRunId] = $counts;
    }

    public function fail(string $screeningRunId, string $message): void {}
}

final class AdjudicationTestDecisions implements ScreeningDecisionRepositoryPort
{
    /** @var list<ScreeningVerdict> */
    public array $recorded;

    /**
     * @param  list<ScreeningVerdict>  $recorded
     */
    public function __construct(array $recorded = [])
    {
        $this->recorded = $recorded;
    }

    public function record(ScreeningVerdict $verdict): void
    {
        $this->recorded[] = $verdict;
    }

    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
    {
        $matches = array_values(array_filter(
            $this->recorded,
            static fn (ScreeningVerdict $verdict): bool => $verdict->projectId === $projectId
                && $verdict->workId === $workId
                && $verdict->stage === $stage,
        ));

        usort($matches, static fn (ScreeningVerdict $a, ScreeningVerdict $b): int => ($b->decidedAt?->getTimestamp() ?? 0) <=> ($a->decidedAt?->getTimestamp() ?? 0));

        return $matches[0] ?? null;
    }

    public function forRun(string $screeningRunId): array
    {
        return array_values(array_filter(
            $this->recorded,
            static fn (ScreeningVerdict $verdict): bool => $verdict->screeningRunId === $screeningRunId,
        ));
    }
}
