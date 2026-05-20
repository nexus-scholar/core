<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderPort;
use Nexus\CitationNetwork\Domain\SnowballDirection;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Search\Domain\Port\DeduplicationPort;
use Nexus\Search\Domain\ScholarlyWork;
use Throwable;

final readonly class SnowballCorpusHandler
{
    public function __construct(
        private SnowballingProviderCollection $providers,
        private DeduplicationPort $deduplication,
    ) {
    }

    public function handle(SnowballCorpus $command): SnowballCorpusResult
    {
        $providers = $this->providers->matching($command->providerAliases);
        $knownCorpus = $command->initialKnownCorpus();
        $currentSeeds = $command->seedCorpus;
        $allNewCorpus = CorpusSlice::empty();
        $rounds = [];
        $providerStats = [];

        for ($depth = 1; $depth <= $command->depth && ! $currentSeeds->isEmpty(); $depth++) {
            $rawDiscovered = [];
            $roundFailureCount = 0;

            foreach ($currentSeeds->all() as $seed) {
                foreach ($providers as $provider) {
                    if ($command->forward) {
                        $works = $this->fetch(
                            $provider,
                            $seed,
                            SnowballDirection::FORWARD,
                            $command->maxCitations,
                        );
                        $providerStats[] = $works['stat'];
                        $rawDiscovered = [...$rawDiscovered, ...$works['works']];
                        $roundFailureCount += $works['stat']->failed() ? 1 : 0;
                    }

                    if ($command->backward) {
                        $works = $this->fetch(
                            $provider,
                            $seed,
                            SnowballDirection::BACKWARD,
                            $command->maxReferences,
                        );
                        $providerStats[] = $works['stat'];
                        $rawDiscovered = [...$rawDiscovered, ...$works['works']];
                        $roundFailureCount += $works['stat']->failed() ? 1 : 0;
                    }
                }
            }

            $rawCorpus = $this->corpusFromWorks($rawDiscovered);
            $deduplicatedRound = $rawCorpus->isEmpty()
                ? CorpusSlice::empty()
                : $this->deduplication->deduplicate($rawCorpus);
            $newRoundCorpus = $deduplicatedRound->subtract($knownCorpus);
            $alreadyKnownCount = max(0, $deduplicatedRound->count() - $newRoundCorpus->count());

            $rounds[] = new SnowballRoundResult(
                depth: $depth,
                inputSeedCount: $currentSeeds->count(),
                discoveredCount: count($rawDiscovered),
                deduplicatedDiscoveredCount: $deduplicatedRound->count(),
                alreadyKnownCount: $alreadyKnownCount,
                netNewCount: $newRoundCorpus->count(),
                providerFailureCount: $roundFailureCount,
            );

            if ($newRoundCorpus->isEmpty()) {
                $currentSeeds = CorpusSlice::empty();
                continue;
            }

            $allNewCorpus = $allNewCorpus->merge($newRoundCorpus);
            $knownCorpus = $this->deduplication->deduplicate($knownCorpus->merge($deduplicatedRound));
            $currentSeeds = $newRoundCorpus;
        }

        return new SnowballCorpusResult(
            projectId: $command->projectId,
            initialSeedCount: $command->seedCorpus->count(),
            depthReached: count($rounds),
            combinedCorpus: $knownCorpus,
            newCorpus: $allNewCorpus,
            rounds: $rounds,
            providerStats: $providerStats,
        );
    }

    /**
     * @return array{works: list<ScholarlyWork>, stat: SnowballProviderStat}
     */
    private function fetch(
        SnowballingProviderPort $provider,
        ScholarlyWork $seed,
        SnowballDirection $direction,
        int $limit,
    ): array {
        $seedId = $seed->primaryId()?->toString() ?? spl_object_hash($seed);
        $startNs = hrtime(true);

        if (! $provider->supportsSnowballing($seed, $direction)) {
            return [
                'works' => [],
                'stat' => new SnowballProviderStat(
                    alias: $provider->alias(),
                    direction: $direction,
                    seedWorkId: $seedId,
                    resultCount: 0,
                    latencyMs: $this->elapsedMs($startNs),
                    skipReason: 'unsupported_seed_or_direction',
                ),
            ];
        }

        try {
            $works = $direction === SnowballDirection::FORWARD
                ? $provider->fetchCitingWorks($seed, $limit)
                : $provider->fetchReferencedWorks($seed, $limit);

            return [
                'works' => array_values($works),
                'stat' => new SnowballProviderStat(
                    alias: $provider->alias(),
                    direction: $direction,
                    seedWorkId: $seedId,
                    resultCount: count($works),
                    latencyMs: $this->elapsedMs($startNs),
                ),
            ];
        } catch (Throwable $error) {
            return [
                'works' => [],
                'stat' => new SnowballProviderStat(
                    alias: $provider->alias(),
                    direction: $direction,
                    seedWorkId: $seedId,
                    resultCount: 0,
                    latencyMs: $this->elapsedMs($startNs),
                    errorMessage: $error->getMessage(),
                ),
            ];
        }
    }

    /**
     * @param list<ScholarlyWork> $works
     */
    private function corpusFromWorks(array $works): CorpusSlice
    {
        return $works === [] ? CorpusSlice::empty() : CorpusSlice::fromWorks(...$works);
    }

    private function elapsedMs(int|float $startNs): float
    {
        return round((hrtime(true) - $startNs) / 1_000_000, 3);
    }
}
