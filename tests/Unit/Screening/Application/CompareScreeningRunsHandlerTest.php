<?php

declare(strict_types=1);

use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsCommand;
use Nexus\Screening\Application\UseCase\CompareScreeningRunsHandler;
use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningDecision;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningRunStatus;
use Nexus\Screening\Domain\ScreeningStage;
use Nexus\Screening\Domain\ScreeningVerdict;

it('compares two screening runs and reports transitions, disagreements, and missing works', function (): void {
    $runs = new CompareRunsTestRepository([
        compareRun('rules-run', ScreeningRunMode::RULES),
        compareRun('human-run', ScreeningRunMode::HUMAN),
    ]);
    $decisions = new CompareDecisionsTestRepository([
        compareVerdict('d1', 'rules-run', 'work-1', ScreeningDecision::INCLUDE, 'rules'),
        compareVerdict('d2', 'rules-run', 'work-2', ScreeningDecision::EXCLUDE, 'rules'),
        compareVerdict('d3', 'rules-run', 'work-3', ScreeningDecision::NEEDS_REVIEW, 'rules'),
        compareVerdict('d4', 'human-run', 'work-1', ScreeningDecision::INCLUDE, 'human'),
        compareVerdict('d5', 'human-run', 'work-2', ScreeningDecision::INCLUDE, 'human'),
        compareVerdict('d6', 'human-run', 'work-4', ScreeningDecision::EXCLUDE, 'human'),
    ]);

    $result = (new CompareScreeningRunsHandler($runs, $decisions))->handle(new CompareScreeningRunsCommand(
        projectId: 'project-1',
        baselineRunId: 'rules-run',
        candidateRunId: 'human-run',
        stage: ScreeningStage::TITLE_ABSTRACT,
    ));

    expect($result->comparableTotal)->toBe(2)
        ->and($result->agreementCount)->toBe(1)
        ->and($result->disagreementCount)->toBe(1)
        ->and($result->transitionCounts['include']['include'])->toBe(1)
        ->and($result->transitionCounts['exclude']['include'])->toBe(1)
        ->and($result->missingInBaseline)->toBe(['work-4'])
        ->and($result->missingInCandidate)->toBe(['work-3'])
        ->and($result->referenceRunId)->toBe('human-run')
        ->and($result->rows)->toHaveCount(4);
});

it('rejects screening runs from another project', function (): void {
    $runs = new CompareRunsTestRepository([
        compareRun('run-a', ScreeningRunMode::LLM_SINGLE, projectId: 'project-1'),
        compareRun('run-b', ScreeningRunMode::LLM_COUNCIL, projectId: 'project-2'),
    ]);

    expect(fn () => (new CompareScreeningRunsHandler($runs, new CompareDecisionsTestRepository))->handle(
        new CompareScreeningRunsCommand('project-1', 'run-a', 'run-b'),
    ))->toThrow(InvalidArgumentException::class, 'requested project');
});

function compareRun(
    string $id,
    ScreeningRunMode $mode,
    string $projectId = 'project-1',
): ScreeningRun {
    return new ScreeningRun(
        id: $id,
        projectId: $projectId,
        stage: ScreeningStage::TITLE_ABSTRACT,
        mode: $mode,
        status: ScreeningRunStatus::COMPLETED,
        criteria: ScreeningCriteria::fromArray(['include' => ['tomato']]),
        counts: ['total' => 1],
    );
}

function compareVerdict(
    string $id,
    string $runId,
    string $workId,
    ScreeningDecision $decision,
    string $source,
): ScreeningVerdict {
    return new ScreeningVerdict(
        id: $id,
        screeningRunId: $runId,
        projectId: 'project-1',
        workId: $workId,
        stage: ScreeningStage::TITLE_ABSTRACT,
        decision: $decision,
        confidence: 0.9,
        source: $source,
        rationale: new ScreeningRationale("{$source} decision"),
        decidedBy: $source,
        decidedAt: new DateTimeImmutable,
        criteriaHash: 'criteria-hash',
    );
}

final class CompareRunsTestRepository implements ScreeningRunRepositoryPort
{
    /** @var array<string, ScreeningRun> */
    private array $runs = [];

    /**
     * @param  list<ScreeningRun>  $runs
     */
    public function __construct(array $runs)
    {
        foreach ($runs as $run) {
            $this->runs[$run->id] = $run;
        }
    }

    public function get(string $screeningRunId): ?ScreeningRun
    {
        return $this->runs[$screeningRunId] ?? null;
    }

    public function start(ScreeningRun $run): void
    {
        $this->runs[$run->id] = $run;
    }

    public function complete(string $screeningRunId, array $counts): void {}

    public function fail(string $screeningRunId, string $message): void {}
}

final class CompareDecisionsTestRepository implements ScreeningDecisionRepositoryPort
{
    /**
     * @param  list<ScreeningVerdict>  $verdicts
     */
    public function __construct(private readonly array $verdicts = []) {}

    public function record(ScreeningVerdict $verdict): void {}

    public function latestForWork(string $projectId, string $workId, ScreeningStage $stage): ?ScreeningVerdict
    {
        return null;
    }

    public function forRun(string $screeningRunId): array
    {
        return array_values(array_filter(
            $this->verdicts,
            static fn (ScreeningVerdict $verdict): bool => $verdict->screeningRunId === $screeningRunId,
        ));
    }
}
