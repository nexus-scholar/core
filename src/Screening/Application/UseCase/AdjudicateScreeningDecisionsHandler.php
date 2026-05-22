<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Domain\ScreeningRationale;
use Nexus\Screening\Domain\ScreeningRun;
use Nexus\Screening\Domain\ScreeningRunMode;
use Nexus\Screening\Domain\ScreeningVerdict;
use Nexus\Shared\Application\CorpusLockPolicy;
use Nexus\Shared\ValueObject\CorpusOperation;

final readonly class AdjudicateScreeningDecisionsHandler
{
    public function __construct(
        private ScreeningRunRepositoryPort $runs,
        private ScreeningDecisionRepositoryPort $decisions,
        private CorpusLockPolicy $lockPolicy,
    ) {}

    public function handle(AdjudicateScreeningDecisionsCommand $command): AdjudicateScreeningDecisionsResult
    {
        $this->lockPolicy->assertCorpusLocked($command->projectId, CorpusOperation::ADJUDICATE);
        $this->lockPolicy->assertWorksBelongToProject(
            $command->projectId,
            $command->workIds(),
            CorpusOperation::ADJUDICATE,
        );

        $runId = $command->runId();
        $this->ensureRun($command, $runId);

        $verdicts = [];
        foreach ($command->decisions as $input) {
            $verdict = $this->verdict($command, $runId, $input);
            $this->decisions->record($verdict);
            $verdicts[] = $verdict;
        }

        $result = AdjudicateScreeningDecisionsResult::fromVerdicts($runId, $verdicts);
        $this->runs->complete($runId, $result->counts());

        return $result;
    }

    private function ensureRun(AdjudicateScreeningDecisionsCommand $command, string $runId): void
    {
        $existing = $this->runs->get($runId);

        if ($existing !== null) {
            if ($existing->projectId !== $command->projectId || $existing->stage !== $command->stage) {
                throw new \InvalidArgumentException('Existing adjudication run does not match the command project and stage.');
            }

            return;
        }

        $this->runs->start(ScreeningRun::start(
            id: $runId,
            projectId: $command->projectId,
            stage: $command->stage,
            mode: ScreeningRunMode::HUMAN,
            criteria: $command->criteria(),
            name: $command->runName,
            config: [
                'mode' => ScreeningRunMode::HUMAN->value,
                'actor_id' => $command->actorId,
            ],
            source: [
                'type' => 'human_adjudication',
                'work_ids' => $command->workIds(),
            ],
            criteriaHash: $command->criteriaHash,
        ));
    }

    private function verdict(
        AdjudicateScreeningDecisionsCommand $command,
        string $runId,
        HumanAdjudicationInput $input,
    ): ScreeningVerdict {
        return new ScreeningVerdict(
            id: bin2hex(random_bytes(16)),
            screeningRunId: $runId,
            projectId: $command->projectId,
            workId: $input->workId,
            stage: $command->stage,
            decision: $input->decision,
            confidence: $input->confidence,
            source: 'human',
            rationale: new ScreeningRationale(
                reason: $input->reason,
                evidence: $this->normalizeList($input->evidence),
                uncertainty: $this->normalizeList($input->uncertainty),
                exclusionBasis: $this->normalizeList($input->exclusionBasis),
            ),
            decidedBy: $command->actorId,
            decidedAt: new \DateTimeImmutable,
            criteriaHash: $command->criteriaHash,
            metadata: [
                'adjudication' => true,
                'source_decision_ids' => $this->normalizeList($input->sourceDecisionIds),
            ],
        );
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizeList(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => trim($value),
            $values,
        ), static fn (string $value): bool => $value !== '')));
    }
}
