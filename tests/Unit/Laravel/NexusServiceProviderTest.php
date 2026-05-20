<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Application\Builder\CitationGraphBuilder;
use Nexus\CitationNetwork\Application\UseCase\AnalyzeNetworkHandler;
use Nexus\CitationNetwork\Application\UseCase\BuildCitationGraphHandler;
use Nexus\CitationNetwork\Application\UseCase\FindShortestCitationPathHandler;
use Nexus\CitationNetwork\Application\UseCase\SnowballCorpusHandler;
use Nexus\CitationNetwork\Domain\Port\GraphAlgorithmPort;
use Nexus\CitationNetwork\Domain\Port\SnowballingProviderCollection;
use Nexus\CitationNetwork\Infrastructure\Graph\MbsoftNetworkMetricsCalculator;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\EuropePmcFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\PmcOaiFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\UnpaywallPdfSource;
use Nexus\Laravel\Persistence\EloquentJobLifecycleRecorder;
use Nexus\Shared\Port\JobLifecycleRecorderPort;

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
        ->and(app(CitationGraphBuilder::class))->toBeInstanceOf(CitationGraphBuilder::class)
        ->and(app(BuildCitationGraphHandler::class))->toBeInstanceOf(BuildCitationGraphHandler::class)
        ->and(app(AnalyzeNetworkHandler::class))->toBeInstanceOf(AnalyzeNetworkHandler::class)
        ->and(app(FindShortestCitationPathHandler::class))->toBeInstanceOf(FindShortestCitationPathHandler::class)
        ->and(app(SnowballingProviderCollection::class))->toBeInstanceOf(SnowballingProviderCollection::class)
        ->and(app(SnowballCorpusHandler::class))->toBeInstanceOf(SnowballCorpusHandler::class);
});

it('registers enabled snowballing providers from provider config', function (): void {
    app()->forgetInstance('nexus.provider_configs');
    app()->forgetInstance(SnowballingProviderCollection::class);
    config()->set('nexus.providers.semantic_scholar.enabled', true);

    $aliases = array_map(
        static fn ($provider): string => $provider->alias(),
        app(SnowballingProviderCollection::class)->all(),
    );

    expect($aliases)->toBe(['semantic_scholar']);

    app()->forgetInstance('nexus.provider_configs');
    app()->forgetInstance(SnowballingProviderCollection::class);
    config()->set('nexus.providers.semantic_scholar.enabled', false);

    expect(app(SnowballingProviderCollection::class)->all())->toBe([]);

    config()->set('nexus.providers.semantic_scholar.enabled', true);
    app()->forgetInstance('nexus.provider_configs');
    app()->forgetInstance(SnowballingProviderCollection::class);
});

it('binds the default sql-backed job lifecycle recorder', function (): void {
    expect(app(JobLifecycleRecorderPort::class))->toBeInstanceOf(EloquentJobLifecycleRecorder::class);
});

it('registers enabled full text sources in retrieval order', function (): void {
    app()->forgetInstance(FullTextSourceCollection::class);
    config()->set('nexus.full_text.sources.unpaywall.email', 'dev@example.com');

    $sources = app(FullTextSourceCollection::class)->all();

    expect($sources[0])->toBeInstanceOf(DirectPdfSource::class)
        ->and($sources[1])->toBeInstanceOf(UnpaywallPdfSource::class)
        ->and($sources[2])->toBeInstanceOf(PmcOaiFullTextSource::class)
        ->and($sources[3])->toBeInstanceOf(EuropePmcFullTextSource::class)
        ->and(array_map(static fn ($source): string => $source->alias(), $sources))->toBe([
            'direct',
            'unpaywall',
            'pmc',
            'europe_pmc',
            'arxiv',
            'openalex',
            'semantic_scholar',
        ]);
});

it('omits disabled or unconfigured full text sources from the collection', function (): void {
    app()->forgetInstance(FullTextSourceCollection::class);
    config()->set('nexus.full_text.sources.unpaywall.email', null);
    config()->set('nexus.full_text.sources.pmc.enabled', false);
    config()->set('nexus.full_text.sources.europe_pmc.enabled', false);
    config()->set('nexus.full_text.sources.openalex.enabled', false);

    $aliases = array_map(
        static fn ($source): string => $source->alias(),
        app(FullTextSourceCollection::class)->all(),
    );

    expect($aliases)->toBe(['direct', 'arxiv', 'semantic_scholar']);
});
