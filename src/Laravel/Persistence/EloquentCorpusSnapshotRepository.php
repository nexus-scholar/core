<?php

declare(strict_types=1);

namespace Nexus\Laravel\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\ValueObject\CorpusSnapshot;

final class EloquentCorpusSnapshotRepository implements CorpusSnapshotRepositoryPort
{
    public function createForLockedProject(
        string $projectId,
        DateTimeImmutable $lockedAt,
        ?string $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): CorpusSnapshot {
        $snapshotId = (string) Str::uuid();
        $now = Carbon::now();
        $memberships = $this->currentMembership($projectId);

        DB::table('corpus_snapshots')->insert([
            'id' => $snapshotId,
            'project_id' => $projectId,
            'locked_at' => $lockedAt,
            'work_count' => count($memberships),
            'created_by' => $actorId,
            'lock_reason' => $reason,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($memberships as $workId => $membership) {
            DB::table('corpus_snapshot_works')->insert([
                'id' => (string) Str::uuid(),
                'snapshot_id' => $snapshotId,
                'work_id' => $workId,
                'search_query_ids' => json_encode($membership['search_query_ids'], JSON_THROW_ON_ERROR),
                'provider_aliases' => json_encode($membership['provider_aliases'], JSON_THROW_ON_ERROR),
                'provenance' => json_encode(['query_works' => $membership['query_works']], JSON_THROW_ON_ERROR),
                'included_at' => $lockedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return new CorpusSnapshot(
            id: $snapshotId,
            projectId: $projectId,
            lockedAt: $lockedAt,
            workCount: count($memberships),
            createdBy: $actorId,
            lockReason: $reason,
            metadata: $metadata,
            createdAt: DateTimeImmutable::createFromInterface($now),
        );
    }

    public function latestForProject(string $projectId): ?CorpusSnapshot
    {
        $row = DB::table('corpus_snapshots')
            ->where('project_id', $projectId)
            ->orderByDesc('locked_at')
            ->orderByDesc('created_at')
            ->first();

        if ($row === null) {
            return null;
        }

        return new CorpusSnapshot(
            id: (string) $row->id,
            projectId: (string) $row->project_id,
            lockedAt: $this->date((string) $row->locked_at),
            workCount: (int) $row->work_count,
            createdBy: $row->created_by === null ? null : (string) $row->created_by,
            lockReason: $row->lock_reason === null ? null : (string) $row->lock_reason,
            metadata: $row->metadata === null ? [] : json_decode((string) $row->metadata, true, flags: JSON_THROW_ON_ERROR),
            createdAt: $row->created_at === null ? null : $this->date((string) $row->created_at),
        );
    }

    /**
     * @return array<string, array{
     *     search_query_ids: list<string>,
     *     provider_aliases: list<string>,
     *     query_works: list<array<string, mixed>>
     * }>
     */
    private function currentMembership(string $projectId): array
    {
        $rows = DB::table('query_works')
            ->join('search_queries', 'search_queries.id', '=', 'query_works.search_query_id')
            ->where('search_queries.project_id', $projectId)
            ->orderBy('query_works.seen_at')
            ->orderBy('query_works.rank')
            ->get([
                'query_works.id as query_work_id',
                'query_works.work_id',
                'query_works.search_query_id',
                'query_works.provider_alias',
                'query_works.provider_work_id',
                'query_works.rank',
                'query_works.seen_at',
                'search_queries.query_text',
            ]);

        $memberships = [];

        foreach ($rows as $row) {
            $workId = (string) $row->work_id;

            $memberships[$workId] ??= [
                'search_query_ids' => [],
                'provider_aliases' => [],
                'query_works' => [],
            ];

            $memberships[$workId]['search_query_ids'][(string) $row->search_query_id] = (string) $row->search_query_id;

            if ($row->provider_alias !== null && trim((string) $row->provider_alias) !== '') {
                $providerAlias = (string) $row->provider_alias;
                $memberships[$workId]['provider_aliases'][$providerAlias] = $providerAlias;
            }

            $memberships[$workId]['query_works'][] = [
                'query_work_id' => (string) $row->query_work_id,
                'search_query_id' => (string) $row->search_query_id,
                'query_text' => (string) $row->query_text,
                'provider_alias' => $row->provider_alias === null ? null : (string) $row->provider_alias,
                'provider_work_id' => $row->provider_work_id === null ? null : (string) $row->provider_work_id,
                'rank' => $row->rank === null ? null : (int) $row->rank,
                'seen_at' => $row->seen_at === null ? null : (string) $row->seen_at,
            ];
        }

        foreach ($memberships as &$membership) {
            $membership['search_query_ids'] = array_values($membership['search_query_ids']);
            $membership['provider_aliases'] = array_values($membership['provider_aliases']);
        }
        unset($membership);

        return $memberships;
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }
}
