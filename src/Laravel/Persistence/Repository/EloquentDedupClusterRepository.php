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
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

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
                    'project_id'             => $cluster->projectId,
                    'strategy'               => $cluster->strategy,
                    'thresholds'             => $cluster->thresholds,
                    'representative_work_id' => $cluster->representative()?->primaryId()?->toString(),
                    'cluster_size'           => $cluster->size(),
                    'confidence'             => $cluster->confidence,
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

        return $this->toDomain($row);
    }

    public function findByProject(string $projectId): array
    {
        $rows = DedupClusterModel::with('members')->where('project_id', $projectId)->get();

        $allWorkIds = [];
        foreach ($rows as $row) {
            if ($row->representative_work_id) {
                $allWorkIds[$row->representative_work_id] = true;
            }
            foreach ($row->members as $memberRow) {
                $allWorkIds[$memberRow->work_id] = true;
            }
        }

        $works = [];
        if (!empty($allWorkIds)) {
            $ids = array_map(fn ($idStr) => new WorkId(WorkIdNamespace::INTERNAL, $idStr), array_keys($allWorkIds));
            $works = $this->workRepository->findManyByIds($ids);
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = $this->toDomain($row, $works);
        }

        return array_filter($results);
    }

    /**
     * @param array<string, \Nexus\Search\Domain\ScholarlyWork> $preloadedWorks
     */
    private function toDomain(DedupClusterModel $row, array $preloadedWorks = []): ?DedupCluster
    {
        $repId = $row->representative_work_id;
        $representative = $repId ? ($preloadedWorks[$repId] ?? $this->workRepository->findById(new WorkId(WorkIdNamespace::INTERNAL, $repId))) : null;

        $members = [];
        foreach ($row->members as $memberRow) {
            $work = $preloadedWorks[$memberRow->work_id] ?? $this->workRepository->findById(new WorkId(WorkIdNamespace::INTERNAL, $memberRow->work_id));
            if ($work) {
                $members[] = $work;
            }
        }

        try {
            return DedupCluster::reconstitute(
                id:             new DedupClusterId($row->id),
                projectId:      $row->project_id,
                representative: $representative,
                members:        $members,
                strategy:       $row->strategy,
                thresholds:     $row->thresholds ?? [],
                confidence:     $row->confidence ? (float) $row->confidence : null,
            );
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
