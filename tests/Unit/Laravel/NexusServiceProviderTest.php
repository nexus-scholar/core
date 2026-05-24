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
use Nexus\Dissemination\Application\UseCase\ExportBibliographyHandler;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler;
use Nexus\Dissemination\Application\UseCase\ExportNetworkHandler;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerCollection;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort;
use Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort;
use Nexus\Dissemination\Domain\Port\FullTextSourceCollection;
use Nexus\Dissemination\Domain\Port\NetworkSerializerCollection;
use Nexus\Dissemination\Domain\Port\SerializerCollection;
use Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource;
use Nexus\Dissemination\Infrastructure\PdfSource\EuropePmcFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\PmcOaiFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\UnpaywallPdfSource;
use Nexus\Dissemination\Infrastructure\Serializer\MbsoftCitationGraphSerializer;
use Nexus\Laravel\Persistence\EloquentCorpusSnapshotRepository;
use Nexus\Laravel\Persistence\EloquentExportHistoryReader;
use Nexus\Laravel\Persistence\EloquentExportHistoryRecorder;
use Nexus\Laravel\Persistence\EloquentFullTextFetchReader;
use Nexus\Laravel\Persistence\EloquentJobLifecycleReader;
use Nexus\Laravel\Persistence\EloquentJobLifecycleRecorder;
use Nexus\Laravel\Persistence\EloquentScreeningWorkSource;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningDecisionRepository;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningRunRepository;
use Nexus\Laravel\Persistence\Repository\EloquentScreeningVoteRepository;
use Nexus\Screening\Application\Port\LlmClientPort;
use Nexus\Screening\Application\Port\ScreeningDecisionRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningPromptRendererPort;
use Nexus\Screening\Application\Port\ScreeningRunRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningVoteRepositoryPort;
use Nexus\Screening\Application\Port\ScreeningWorkSourcePort;
use Nexus\Screening\Application\UseCase\ScreenCorpusHandler;
use Nexus\Screening\Application\UseCase\ScreenWorkHandler;
use Nexus\Screening\Domain\CouncilDecisionAggregator;
use Nexus\Screening\Infrastructure\Llm\DisabledLlmClient;
use Nexus\Screening\Infrastructure\Prompt\DefaultScreeningPromptRenderer;
use Nexus\Search\Application\ProviderExecution\ProviderSearchExecutorPort;
use Nexus\Search\Application\ProviderExecution\SequentialProviderSearchExecutor;
use Nexus\Shared\Port\CorpusSnapshotRepositoryPort;
use Nexus\Shared\Port\JobLifecycleReaderPort;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\Port\ProjectCorpusWorksPort;
use Nexus\Shared\Port\ProjectWorkMembershipPort;

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
    config()->set('nexus.providers.openalex.enabled', true);
    config()->set('nexus.providers.crossref.enabled', true);
    config()->set('nexus.providers.semantic_scholar.enabled', true);

    $aliases = array_map(
        static fn ($provider): string => $provider->alias(),
        app(SnowballingProviderCollection::class)->all(),
    );

    expect($aliases)->toBe(['openalex', 'crossref', 'semantic_scholar']);

    app()->forgetInstance('nexus.provider_configs');
    app()->forgetInstance(SnowballingProviderCollection::class);
    config()->set('nexus.providers.openalex.enabled', false);
    config()->set('nexus.providers.crossref.enabled', false);
    config()->set('nexus.providers.semantic_scholar.enabled', false);

    expect(app(SnowballingProviderCollection::class)->all())->toBe([]);

    config()->set('nexus.providers.openalex.enabled', true);
    config()->set('nexus.providers.crossref.enabled', true);
    config()->set('nexus.providers.semantic_scholar.enabled', true);
    app()->forgetInstance('nexus.provider_configs');
    app()->forgetInstance(SnowballingProviderCollection::class);
});

it('binds the default sql-backed job lifecycle recorder', function (): void {
    expect(app(JobLifecycleRecorderPort::class))->toBeInstanceOf(EloquentJobLifecycleRecorder::class);
});

it('binds the package-owned provider search executor', function (): void {
    expect(app(ProviderSearchExecutorPort::class))->toBeInstanceOf(SequentialProviderSearchExecutor::class);
});

it('binds host read-side APIs', function (): void {
    expect(app(JobLifecycleReaderPort::class))->toBeInstanceOf(EloquentJobLifecycleReader::class)
        ->and(app(ExportHistoryReaderPort::class))->toBeInstanceOf(EloquentExportHistoryReader::class)
        ->and(app(FullTextFetchReaderPort::class))->toBeInstanceOf(EloquentFullTextFetchReader::class);
});

it('binds screening repositories and council aggregation services', function (): void {
    expect(app(ScreeningRunRepositoryPort::class))->toBeInstanceOf(EloquentScreeningRunRepository::class)
        ->and(app(ScreeningDecisionRepositoryPort::class))->toBeInstanceOf(EloquentScreeningDecisionRepository::class)
        ->and(app(ScreeningVoteRepositoryPort::class))->toBeInstanceOf(EloquentScreeningVoteRepository::class)
        ->and(app(ScreeningWorkSourcePort::class))->toBeInstanceOf(EloquentScreeningWorkSource::class)
        ->and(app(CouncilDecisionAggregator::class))->toBeInstanceOf(CouncilDecisionAggregator::class)
        ->and(app(ScreeningPromptRendererPort::class))->toBeInstanceOf(DefaultScreeningPromptRenderer::class)
        ->and(app(LlmClientPort::class))->toBeInstanceOf(DisabledLlmClient::class)
        ->and(app(ScreenCorpusHandler::class))->toBeInstanceOf(ScreenCorpusHandler::class)
        ->and(app(ScreenWorkHandler::class))->toBeInstanceOf(ScreenWorkHandler::class);
});

it('binds corpus snapshot and membership authority services', function (): void {
    expect(app(CorpusSnapshotRepositoryPort::class))->toBeInstanceOf(EloquentCorpusSnapshotRepository::class)
        ->and(app(ProjectWorkMembershipPort::class))->toBe(app(ProjectCorpusWorksPort::class));
});

it('uses structured-output-compatible default council screening models', function (): void {
    expect(config('nexus.screening.llm.council.models'))->toBe([
        'openai/gpt-4.1-mini',
        'google/gemini-2.5-flash',
        'mistralai/mistral-small-2603',
    ]);
});

it('binds export history and export handlers', function (): void {
    expect(app(ExportHistoryPort::class))->toBeInstanceOf(EloquentExportHistoryRecorder::class)
        ->and(app(SerializerCollection::class)->all())->toHaveCount(5)
        ->and(app(NetworkSerializerCollection::class)->all())->toHaveCount(3)
        ->and(app(CitationGraphSerializerCollection::class)->all())->toHaveCount(1)
        ->and(app(CitationGraphSerializerCollection::class)->all()[0])->toBeInstanceOf(MbsoftCitationGraphSerializer::class)
        ->and(app(ExportBibliographyHandler::class))->toBeInstanceOf(ExportBibliographyHandler::class)
        ->and(app(ExportNetworkHandler::class))->toBeInstanceOf(ExportNetworkHandler::class)
        ->and(app(ExportCitationGraphHandler::class))->toBeInstanceOf(ExportCitationGraphHandler::class);
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
