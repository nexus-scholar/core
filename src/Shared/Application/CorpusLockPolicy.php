<?php

declare(strict_types=1);

namespace Nexus\Shared\Application;

use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Exception\ProjectNotFoundException;
use Nexus\Shared\Exception\ProjectNotLockedException;
use Nexus\Shared\Port\ProjectLockLifecyclePort;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;
use Nexus\Shared\ValueObject\CorpusOperation;

final readonly class CorpusLockPolicy
{
    public function __construct(
        private ProjectLockPort $locks,
        private ProjectWorkMembershipPort $membership,
        private ?ProjectLockLifecyclePort $lifecycle = null,
    ) {}

    public function assertCorpusMutable(string $projectId, CorpusOperation $operation): void
    {
        if ($this->isLocked($projectId)) {
            throw new ProjectLockedException(
                sprintf('Project %s is locked; %s cannot mutate the corpus.', $projectId, $operation->value),
            );
        }
    }

    public function assertCorpusLocked(string $projectId, CorpusOperation $operation): void
    {
        if (! $this->isLocked($projectId)) {
            throw new ProjectNotLockedException(
                sprintf('Project %s must be locked before %s.', $projectId, $operation->value),
            );
        }
    }

    public function isLocked(string $projectId): bool
    {
        return $this->locks->isLocked($projectId);
    }

    /**
     * @param  list<string>  $workIds
     */
    public function assertWorksBelongToProject(string $projectId, array $workIds, CorpusOperation $operation): void
    {
        $missing = $this->membership->missingWorkIds($projectId, $this->normalizeWorkIds($workIds));

        if ($missing !== []) {
            throw new \InvalidArgumentException(sprintf(
                'Project %s cannot %s works outside its corpus: %s.',
                $projectId,
                $operation->value,
                implode(', ', $missing),
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function exportMetadata(?string $projectId): array
    {
        if ($projectId === null || trim($projectId) === '') {
            return [
                'project_locked' => false,
                'locked_at' => null,
                'lock_status' => null,
                'citable' => false,
            ];
        }

        if ($this->lifecycle !== null) {
            try {
                $state = $this->lifecycle->status($projectId);

                return [
                    'project_locked' => $state->isLocked,
                    'locked_at' => $state->lockedAt?->format(DATE_ATOM),
                    'lock_status' => $state->status,
                    'citable' => $state->isLocked,
                ];
            } catch (ProjectNotFoundException) {
                return [
                    'project_locked' => false,
                    'locked_at' => null,
                    'lock_status' => 'unknown_project',
                    'citable' => false,
                ];
            }
        }

        $locked = $this->locks->isLocked($projectId);

        return [
            'project_locked' => $locked,
            'locked_at' => null,
            'lock_status' => $locked ? 'locked' : 'unknown',
            'citable' => $locked,
        ];
    }

    /**
     * @param  list<string>  $workIds
     * @return list<string>
     */
    private function normalizeWorkIds(array $workIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (string $workId): string => trim($workId),
            $workIds,
        ), static fn (string $workId): bool => $workId !== '')));
    }
}
