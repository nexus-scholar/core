<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\DedupClusterModel;
use Nexus\Laravel\Model\ClusterMemberModel;

final class EloquentDedupClusterRepository
{
    public function save(array $clusterData, array $members): void
    {
        DB::transaction(function () use ($clusterData, $members): void {
            $clusterRow = DedupClusterModel::updateOrCreate(
                ['id' => $clusterData['id']],
                $clusterData
            );

            $currentMemberIds = array_map(fn ($m) => $m['work_id'], $members);

            ClusterMemberModel::where('cluster_id', $clusterRow->id)
                ->whereNotIn('work_id', $currentMemberIds)
                ->delete();

            foreach ($members as $member) {
                ClusterMemberModel::updateOrCreate(
                    [
                        'cluster_id' => $clusterRow->id,
                        'work_id'    => $member['work_id'],
                    ],
                    array_merge(['id' => (string) Str::uuid()], $member)
                );
            }
        });
    }

    public function findById(string $clusterId): ?DedupClusterModel
    {
        return DedupClusterModel::with('members')->find($clusterId);
    }

    public function findByProject(string $projectId): array
    {
        return DedupClusterModel::with('members')
            ->where('project_id', $projectId)
            ->get()
            ->all();
    }
}

