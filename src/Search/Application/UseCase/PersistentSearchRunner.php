<?php

declare(strict_types=1);

namespace Nexus\Search\Application\UseCase;

use Nexus\Search\Application\Aggregator\AggregatedResult;
use Nexus\Search\Application\Port\SearchExecutorPort;
use Nexus\Search\Application\Port\SearchRunRecorderPort;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\Exception\ProjectLockedException;
use Nexus\Shared\Port\ProjectLockPort;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Throwable;

final class PersistentSearchRunner implements SearchExecutorPort
{
    public function __construct(
        private readonly SearchAcrossProvidersHandler $inner,
        private readonly SearchRunRecorderPort $recorder,
        private readonly ?ProjectLockPort $projectLocks = null,
    ) {}

    public function handle(SearchAcrossProviders $command): AggregatedResult
    {
        $query = $command->query;
        if ($this->projectLocks?->isLocked($query->projectId)) {
            throw new ProjectLockedException("Cannot perform search on locked project {$query->projectId}");
        }

        $this->recorder->recordStarted($query);

        try {
            $result = $this->inner->handle($command);

            foreach ($result->providerStats as $stat) {
                $this->recorder->recordProviderStat($query, $stat);
            }

            foreach (array_values($result->corpus->all()) as $index => $work) {
                $providerAlias = $this->providerAliasFor($work, $query->providerAliases);
                $providerWorkId = $this->providerWorkIdFor($work, $providerAlias);

                if ($work->primaryId() === null || $providerWorkId === null) {
                    continue;
                }

                $this->recorder->recordWork(
                    query: $query,
                    work: $work,
                    providerAlias: $providerAlias,
                    providerWorkId: $providerWorkId,
                    rank: $index + 1,
                );
            }

            $this->recorder->recordCompleted($query, $result);

            return $result;
        } catch (Throwable $error) {
            $this->recorder->recordFailed($query, $error);

            throw $error;
        }
    }

    /**
     * @param  list<string>  $selectedAliases
     */
    private function providerAliasFor(ScholarlyWork $work, array $selectedAliases): string
    {
        $sourceProvider = strtolower(trim($work->sourceProvider()));

        if ($sourceProvider !== '') {
            return $sourceProvider;
        }

        return $selectedAliases[0] ?? 'unknown';
    }

    private function providerWorkIdFor(ScholarlyWork $work, string $providerAlias): ?string
    {
        $namespace = match ($providerAlias) {
            'arxiv' => WorkIdNamespace::ARXIV,
            'crossref' => WorkIdNamespace::DOI,
            'doaj' => WorkIdNamespace::DOAJ,
            'ieee' => WorkIdNamespace::IEEE,
            'openalex' => WorkIdNamespace::OPENALEX,
            'pubmed' => WorkIdNamespace::PUBMED,
            'semantic_scholar' => WorkIdNamespace::S2,
            default => null,
        };

        $providerId = $namespace === null ? null : $work->ids()->findByNamespace($namespace);

        return $providerId?->value ?? $work->primaryId()?->value;
    }
}
