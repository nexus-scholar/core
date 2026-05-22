<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\UseCase;

use Nexus\Screening\Domain\ScreeningCriteria;
use Nexus\Screening\Domain\ScreeningStage;

final readonly class AdjudicateScreeningDecisionsCommand
{
    /**
     * @param  non-empty-list<HumanAdjudicationInput>  $decisions
     */
    public function __construct(
        public string $projectId,
        public string $actorId,
        public ScreeningStage $stage,
        public string $criteriaHash,
        public array $decisions,
        public ?string $screeningRunId = null,
        public ?string $runName = null,
    ) {
        if (trim($projectId) === '') {
            throw new \InvalidArgumentException('Adjudication requires a project id.');
        }

        if (trim($actorId) === '') {
            throw new \InvalidArgumentException('Adjudication requires an actor id.');
        }

        if (trim($criteriaHash) === '') {
            throw new \InvalidArgumentException('Adjudication requires a criteria hash.');
        }

        if ($decisions === []) {
            throw new \InvalidArgumentException('Adjudication requires at least one decision.');
        }
    }

    public function runId(): string
    {
        return $this->screeningRunId ?? bin2hex(random_bytes(16));
    }

    public function criteria(): ScreeningCriteria
    {
        return ScreeningCriteria::fromArray([
            'human_adjudication' => [
                'criteria_hash' => $this->criteriaHash,
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    public function workIds(): array
    {
        return array_values(array_map(
            static fn (HumanAdjudicationInput $input): string => $input->workId,
            $this->decisions,
        ));
    }
}
