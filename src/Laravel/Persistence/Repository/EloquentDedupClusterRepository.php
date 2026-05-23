<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence\Repository;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Deduplication\Domain\DedupCluster;
use Nexus\Deduplication\Domain\DedupClusterId;
use Nexus\Deduplication\Domain\Port\ClusterRepositoryPort;
use Nexus\Laravel\Model\ClusterMemberModel;
use Nexus\Laravel\Model\DedupClusterModel;
use Nexus\Search\Domain\Port\WorkRepositoryPort;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class EloquentDedupClusterRepository implements ClusterRepositoryPort
{
    public function __construct(
        private readonly WorkRepositoryPort $workRepository
    ) {}

    public function save(DedupCluster $cluster): void
    {
        DB::transaction(function () use ($cluster): void {
            $representativeWorkId = $cluster->representative() !== null
                ? $this->internalIdForWork($cluster->representative())
                : null;

            $clusterRow = DedupClusterModel::updateOrCreate(
                ['id' => $cluster->id->toString()],
                [
                    'project_id' => $cluster->projectId,
                    'strategy' => $cluster->strategy,
                    'thresholds' => $cluster->thresholds,
                    'representative_work_id' => $representativeWorkId,
                    'cluster_size' => $cluster->size(),
                    'confidence' => $cluster->confidence,
                    'is_locked' => $cluster->isLocked,
                ]
            );

            $currentMemberIds = array_values(array_filter(array_map(
                fn ($member) => $this->internalIdForWork($member),
                $cluster->members()
            )));

            $memberQuery = ClusterMemberModel::where('cluster_id', $clusterRow->id);

            if ($currentMemberIds === []) {
                $memberQuery->delete();
            } else {
                $memberQuery->whereNotIn('work_id', $currentMemberIds)->delete();
            }

            foreach ($cluster->members() as $member) {
                $memberId = $this->internalIdForWork($member);

                if ($memberId === null) {
                    continue;
                }

                ClusterMemberModel::updateOrCreate(
                    [
                        'cluster_id' => $clusterRow->id,
                        'work_id' => $memberId,
                    ],
                    ['id' => (string) Str::uuid()]
                );
            }
        });
    }

    public function findById(string $clusterId): ?DedupCluster
    {
        $row = DedupClusterModel::with('members')->find($clusterId);
        if (! $row) {
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
        if (! empty($allWorkIds)) {
            $ids = array_map(fn ($idStr) => new WorkId(WorkIdNamespace::INTERNAL, $idStr), array_keys($allWorkIds));
            $loadedWorks = $this->workRepository->findManyByIds($ids);

            // Re-key by internal ID to avoid N+1 in toDomain
            foreach ($loadedWorks as $work) {
                $internalId = $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL);
                if ($internalId) {
                    $works['internal:'.$internalId->value] = $work;
                }
            }
        }

        $results = [];
        foreach ($rows as $row) {
            $domain = $this->toDomain($row, $works);
            if ($domain) {
                $results[] = $domain;
            }
        }

        return $results;
    }

    /**
     * @param  array<string, ScholarlyWork>  $preloadedWorks
     */
    private function toDomain(DedupClusterModel $row, array $preloadedWorks = []): ?DedupCluster
    {
        $repIdStr = $row->representative_work_id ? 'internal:'.$row->representative_work_id : null;
        $representative = $repIdStr ? ($preloadedWorks[$repIdStr] ?? $this->workRepository->findById(new WorkId(WorkIdNamespace::INTERNAL, $row->representative_work_id))) : null;

        $members = [];
        foreach ($row->members as $memberRow) {
            $mIdStr = 'internal:'.$memberRow->work_id;
            $work = $preloadedWorks[$mIdStr] ?? $this->workRepository->findById(new WorkId(WorkIdNamespace::INTERNAL, $memberRow->work_id));
            if ($work) {
                $members[] = $work;
            }
        }

        try {
            return DedupCluster::reconstitute(
                id: new DedupClusterId($row->id),
                projectId: $row->project_id,
                representative: $representative,
                members: $members,
                strategy: $row->strategy ?? 'default',
                thresholds: $row->thresholds ?? [],
                confidence: $row->confidence ? (float) $row->confidence : null,
                isLocked: (bool) $row->is_locked,
            );
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    private function internalIdForWork(ScholarlyWork $work): ?string
    {
        $internalId = $work->ids()->findByNamespace(WorkIdNamespace::INTERNAL);

        if ($internalId !== null) {
            return $internalId->value;
        }

        $primaryId = $work->primaryId();

        if ($primaryId === null) {
            return null;
        }

        return $this->workRepository
            ->findById($primaryId)
            ?->ids()
            ->findByNamespace(WorkIdNamespace::INTERNAL)
            ?->value;
    }
}
