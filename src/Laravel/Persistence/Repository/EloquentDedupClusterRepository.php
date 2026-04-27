<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\DedupClusterModel;
use Nexus\Laravel\Model\ClusterMemberModel;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Search\Domain\Port\WorkRepositoryPort;

final class EloquentDedupClusterRepository implements ClusterRepositoryPort
{
    public function __construct(
        private readonly WorkRepositoryPort $workRepository
    ) {
    }

    public function save(DedupCluster $cluster): void
    {
        DB::transaction(function () use ($cluster): void {
            $clusterRow = DedupClusterModel::updateOrCreate(
                ['id' => $cluster->id->toString()],
                [
                    'representative_work_id' => $cluster->representative()->primaryId()?->toString(),
                ]
            );

            $currentMemberIds = array_map(
                fn ($m) => $m->primaryId()?->toString(),
                $cluster->members()
            );

            ClusterMemberModel::where('cluster_id', $clusterRow->id)
                ->whereNotIn('work_id', $currentMemberIds)
                ->delete();

            foreach ($cluster->members() as $member) {
                ClusterMemberModel::updateOrCreate(
                    [
                        'cluster_id' => $clusterRow->id,
                        'work_id'    => $member->primaryId()?->toString(),
                    ],
                    ['id' => (string) Str::uuid()]
                );
            }
        });
    }

    public function findById(string $clusterId): ?DedupCluster
    {
        $row = DedupClusterModel::with('members')->find($clusterId);
        if (!$row) {
            return null;
        }

        $representative = $this->workRepository->findById(
            new \Nexus\Shared\ValueObject\WorkId(
                \Nexus\Shared\ValueObject\WorkIdNamespace::INTERNAL,
                $row->representative_work_id
            )
        );

        if (!$representative) {
            return null; // Or throw exception?
        }

        $members = [];
        foreach ($row->members as $memberRow) {
            $work = $this->workRepository->findById(
                new \Nexus\Shared\ValueObject\WorkId(
                    \Nexus\Shared\ValueObject\WorkIdNamespace::INTERNAL,
                    $memberRow->work_id
                )
            );
            if ($work) {
                $members[] = $work;
            }
        }

        return DedupCluster::reconstitute(
            new DedupClusterId($row->id),
            $representative,
            $members
        );
    }

    public function findByProject(string $projectId): array
    {
        return DedupClusterModel::where('project_id', $projectId)
            ->get()
            ->map(fn ($row) => $this->findById($row->id))
            ->filter()
            ->all();
    }
}
