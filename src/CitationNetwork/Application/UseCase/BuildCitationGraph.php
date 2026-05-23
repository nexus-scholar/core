<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\UseCase;

use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;

final readonly class BuildCitationGraph
{
    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByWorkId
     * @param  array<string, list<WorkId|string>>  $citingWorkIdsByCitedWorkId
     */
    private function __construct(
        public string $projectId,
        public CitationGraphType $type,
        public array $works,
        public array $referencesByWorkId = [],
        public array $citingWorkIdsByCitedWorkId = [],
        public bool $persist = true,
    ) {}

    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByCitingWorkId
     */
    public static function directCitation(
        string $projectId,
        array $works,
        array $referencesByCitingWorkId,
        bool $persist = true,
    ): self {
        return new self(
            projectId: $projectId,
            type: CitationGraphType::CITATION,
            works: $works,
            referencesByWorkId: $referencesByCitingWorkId,
            persist: $persist,
        );
    }

    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByCitingWorkId
     * @param  array<string, list<WorkId|string>>  $citingWorkIdsByCitedWorkId
     */
    public static function coCitation(
        string $projectId,
        array $works,
        array $referencesByCitingWorkId = [],
        array $citingWorkIdsByCitedWorkId = [],
        bool $persist = true,
    ): self {
        return new self(
            projectId: $projectId,
            type: CitationGraphType::CO_CITATION,
            works: $works,
            referencesByWorkId: $referencesByCitingWorkId,
            citingWorkIdsByCitedWorkId: $citingWorkIdsByCitedWorkId,
            persist: $persist,
        );
    }

    /**
     * @param  list<ScholarlyWork>  $works
     * @param  array<string, list<WorkId|string>>  $referencesByWorkId
     */
    public static function bibliographicCoupling(
        string $projectId,
        array $works,
        array $referencesByWorkId,
        bool $persist = true,
    ): self {
        return new self(
            projectId: $projectId,
            type: CitationGraphType::BIBLIOGRAPHIC_COUPLING,
            works: $works,
            referencesByWorkId: $referencesByWorkId,
            persist: $persist,
        );
    }
}
