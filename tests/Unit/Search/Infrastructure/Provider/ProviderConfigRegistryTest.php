<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use Nexus\Search\Infrastructure\Provider\ProviderConfigRegistry;

it('applies provider configuration overrides from arrays', function (): void {
    $configs = ProviderConfigRegistry::fromArray([
        'arxiv' => [
            'enabled' => false,
        ],
        'ieee' => [
            'enabled' => true,
            'api_key' => ' ieee-key ',
            'rate_limit' => '1.5',
            'timeout' => '12',
            'max_retries' => '2',
        ],
        'semantic_scholar' => [
            'api_key' => 's2-key',
            'rate_per_second' => 8,
            'timeout_seconds' => 20,
        ],
    ], 'ops@example.com');

    expect($configs['arxiv']->enabled)->toBeFalse()
        ->and($configs['ieee']->enabled)->toBeTrue()
        ->and($configs['ieee']->apiKey)->toBe('ieee-key')
        ->and($configs['ieee']->ratePerSecond)->toBe(1.5)
        ->and($configs['ieee']->timeoutSeconds)->toBe(12)
        ->and($configs['ieee']->maxRetries)->toBe(2)
        ->and($configs['semantic_scholar']->apiKey)->toBe('s2-key')
        ->and($configs['semantic_scholar']->ratePerSecond)->toBe(8.0)
        ->and($configs['semantic_scholar']->timeoutSeconds)->toBe(20)
        ->and($configs['openalex']->mailTo)->toBe('ops@example.com');
});

it('keeps ieee disabled by default when no api key is configured', function (): void {
    $configs = ProviderConfigRegistry::fromArray([]);

    expect($configs['ieee']->enabled)->toBeFalse();
});

it('rejects invalid provider numeric configuration', function (): void {
    expect(fn () => ProviderConfigRegistry::fromArray([
        'openalex' => [
            'rate_limit' => 0,
        ],
    ]))->toThrow(\InvalidArgumentException::class, 'rate limit must be greater than zero');
});
