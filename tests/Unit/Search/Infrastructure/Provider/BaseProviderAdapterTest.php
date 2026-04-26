<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use Nexus\Search\Domain\Exception\ProviderUnavailable;
use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Psr\Log\AbstractLogger;
use Nexus\Search\Infrastructure\Provider\BaseProviderAdapter;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;

final class StubAdapter extends BaseProviderAdapter
{
    public function alias(): string
    {
        return 'stub';
    }

    public function supports(\Nexus\Shared\ValueObject\WorkIdNamespace $ns): bool
    {
        return true;
    }

    public function fetchById(\Nexus\Shared\ValueObject\WorkId $id): ?ScholarlyWork
    {
        return null;
    }

    public function search(SearchQuery $query): array
    {
        $this->request('http://example.com');
        return [];
    }

    protected function normalize(array $raw, SearchQuery $query): ScholarlyWork
    {
        throw new \RuntimeException('Not implemented');
    }

    protected function paginationParams(SearchQuery $query): array
    {
        return [];
    }

    protected function extractItems(array $body): array
    {
        return [];
    }
}

it('logs a warning on 429 retry and error on exhaustion', function (): void {
    $logger = new class extends AbstractLogger {
        public array $logs = [];
        
        public function log($level, \Stringable|string $message, array $context = []): void
        {
            $this->logs[] = ['level' => $level, 'message' => (string) $message];
        }
        
        public function hasWarningThatContains(string $str): bool {
            foreach ($this->logs as $log) {
                if ($log['level'] === 'warning' && str_contains($log['message'], $str)) return true;
            }
            return false;
        }

        public function hasErrorThatContains(string $str): bool {
            foreach ($this->logs as $log) {
                if ($log['level'] === 'error' && str_contains($log['message'], $str)) return true;
            }
            return false;
        }
    };
    
    $http = new class implements HttpClientPort {
        public function get(string $url, array $query = [], array $headers = []): HttpResponse
        {
            return new HttpResponse(
                statusCode: 429,
                body: [],
                rawBody: '',
                headers: [],
            );
        }
    };

    $rateLimiter = new class implements RateLimiterPort {
        public function waitForToken(): void {}
        public function remainingTokens(): int { return 10; }
        public function capacity(): int { return 10; }
        public function tryConsume(): bool { return true; }
        public function ratePerSecond(): float { return 1.0; }
    };

    $config = new ProviderConfig(
        alias: 'stub',
        baseUrl: 'http://example.com',
        ratePerSecond: 1.0,
        maxRetries: 2
    );

    $adapter = new StubAdapter($http, $rateLimiter, $config, $logger);

    $query = new SearchQuery(
        term: new SearchTerm('test'),
    );

    // With maxRetries = 2, it will attempt once, log warning, attempt second time, log error and throw.
    // However, sleep(1) is called on backoff. This will take ~1 second to run.
    expect(fn() => $adapter->search($query))->toThrow(ProviderUnavailable::class);

    expect($logger->hasWarningThatContains('rate limited'))->toBeTrue();
    expect($logger->hasErrorThatContains('failed permanently'))->toBeTrue();
});
