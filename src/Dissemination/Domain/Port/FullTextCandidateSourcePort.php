<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Shared\Domain\ScholarlyWork;

interface FullTextCandidateSourcePort extends FullTextSourcePort
{
    public function resolveCandidate(ScholarlyWork $work): ?FullTextSourceCandidate;
}
