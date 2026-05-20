<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator;

it('builds provider configs from laravel package config', function (): void {
    app()->forgetInstance('nexus.provider_configs');

    config()->set('nexus.mail_to', 'ops@example.com');
    config()->set('nexus.providers.ieee', [
        'enabled' => true,
        'api_key' => 'ieee-key',
        'rate_limit' => 1.0,
        'timeout' => 9,
        'max_retries' => 2,
    ]);
    config()->set('nexus.providers.arxiv.enabled', false);

    $configs = app('nexus.provider_configs');

    expect($configs['ieee']->enabled)->toBeTrue()
        ->and($configs['ieee']->apiKey)->toBe('ieee-key')
        ->and($configs['ieee']->ratePerSecond)->toBe(1.0)
        ->and($configs['ieee']->timeoutSeconds)->toBe(9)
        ->and($configs['ieee']->maxRetries)->toBe(2)
        ->and($configs['arxiv']->enabled)->toBeFalse()
        ->and($configs['openalex']->mailTo)->toBe('ops@example.com');
});

it('resolves citation network graph services from the container', function (): void {
    expect(app(GraphAlgorithmPort::class))->toBeInstanceOf(MbsoftNetworkMetricsCalculator::class)
        ->and(app(CitationGraphBuilder::class))->toBeInstanceOf(CitationGraphBuilder::class);
});
