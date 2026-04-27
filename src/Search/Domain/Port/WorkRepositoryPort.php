<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

interface WorkRepositoryPort
{
    public function findById(WorkId $id): ?ScholarlyWork;
    public function save(ScholarlyWork $work): void;
}
