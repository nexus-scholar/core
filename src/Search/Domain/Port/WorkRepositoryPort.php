<?php

declare(strict_types=1);

namespace Nexus\Search\Domain\Port;

use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

interface WorkRepositoryPort
{
    public function findById(WorkId $id): ?ScholarlyWork;
    
    /** 
     * @param WorkId[] $ids
     * @return ScholarlyWork[] Keyed by WorkId string
     */
    public function findManyByIds(array $ids): array;

    public function save(ScholarlyWork $work): void;
}
