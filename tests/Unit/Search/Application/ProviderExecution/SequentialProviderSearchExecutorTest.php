<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Application\ProviderExecution;

use Nexus\Search\Application\ProviderExecution\SequentialProviderSearchExecutor;
use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\AcademicProviderPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

it('executes provider searches and returns works with provider stats', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1234/executor')),
        title: 'Executor Boundary',
        sourceProvider: 'first',
    );

    $result = (new SequentialProviderSearchExecutor)->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            providerStub('first', [$work]),
            providerStub('second', []),
        ],
    );

    expect($result->works())->toBe([$work])
        ->and($result->stats())->toHaveCount(2)
        ->and($result->stats()[0]->alias)->toBe('first')
        ->and($result->stats()[0]->resultCount)->toBe(1)
        ->and($result->stats()[0]->skipReason)->toBeNull()
        ->and($result->stats()[1]->alias)->toBe('second')
        ->and($result->stats()[1]->resultCount)->toBe(0)
        ->and($result->stats()[1]->skipReason)->toBeNull();
});

it('captures provider failures without stopping remaining providers', function (): void {
    $work = ScholarlyWork::reconstitute(
        ids: new WorkIdSet(new WorkId(WorkIdNamespace::DOI, '10.1234/survives')),
        title: 'Successful Provider Still Runs',
        sourceProvider: 'healthy',
    );

    $result = (new SequentialProviderSearchExecutor)->execute(
        new SearchQuery(new SearchTerm('executor')),
        [
            providerStub('broken', [], new ProviderUnavailable('broken', 'temporary failure')),
            providerStub('healthy', [$work]),
        ],
    );

    expect($result->works())->toBe([$work])
        ->and($result->stats())->toHaveCount(2)
        ->and($result->stats()[0]->alias)->toBe('broken')
        ->and($result->stats()[0]->resultCount)->toBe(0)
        ->and($result->stats()[0]->skipReason)->toBe('Provider "broken" is unavailable: temporary failure')
        ->and($result->stats()[1]->alias)->toBe('healthy')
        ->and($result->stats()[1]->resultCount)->toBe(1);
});

/**
 * @param  ScholarlyWork[]  $works
 */
function providerStub(string $alias, array $works, ?\Throwable $failure = null): AcademicProviderPort
{
    return new class($alias, $works, $failure) implements AcademicProviderPort
    {
        /**
         * @param  ScholarlyWork[]  $works
         */
        public function __construct(
            private readonly string $providerAlias,
            private readonly array $works,
            private readonly ?\Throwable $failure = null,
        ) {}

        public function alias(): string
        {
            return $this->providerAlias;
        }

        public function search(SearchQuery $query): array
        {
            if ($this->failure !== null) {
                throw $this->failure;
            }

            return $this->works;
        }

        public function fetchById(WorkId $id): ?ScholarlyWork
        {
            return null;
        }

        public function supports(WorkIdNamespace $ns): bool
        {
            return true;
        }
    };
}
