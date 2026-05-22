<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Port;

use Nexus\Screening\Domain\ScreeningVote;

interface ScreeningVoteRepositoryPort
{
    public function record(ScreeningVote $vote): void;

    /**
     * @return list<ScreeningVote>
     */
    public function forDecision(string $screeningDecisionId): array;
}
