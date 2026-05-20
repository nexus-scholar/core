<?php

declare(strict_types=1);

use Nexus\Dissemination\Domain\FullTextArtifactType;
use Nexus\Dissemination\Infrastructure\PdfSource\EuropePmcFullTextSource;
use Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig;
use Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Tests\Support\FakeHttpClient;
use Tests\Support\SpyRateLimiter;

function europePmcWork(?array $rawData = ['pmcid' => 'PMC11260570']): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.5555/europe-pmc')]),
        title: 'Europe PMC Test Work',
        sourceProvider: 'fixture',
        rawData: $rawData,
    );
}

function europePmcSource(
    FakeHttpClient $http,
    ?SpyRateLimiter $limiter = null,
    bool $preferPdf = true,
    bool $preferXml = true,
): EuropePmcFullTextSource {
    return new EuropePmcFullTextSource(
        new OaHttpClient(
            $http,
            $limiter ?? new SpyRateLimiter(),
            FullTextSourceConfig::fromArray(
                'europe_pmc',
                'https://www.ebi.ac.uk/europepmc/webservices/rest',
                ['timeout' => 15, 'prefer_pdf' => $preferPdf, 'prefer_xml' => $preferXml],
            ),
        ),
    );
}

it('resolves Europe PMC open PDF links from a core search response', function (): void {
    $http = new FakeHttpClient(new HttpResponse(200, [
        'resultList' => [
            'result' => [[
                'id' => '39040661',
                'source' => 'MED',
                'doi' => '10.5555/europe-pmc',
                'pmcid' => 'PMC11260570',
                'hasPDF' => 'Y',
                'isOpenAccess' => 'Y',
                'license' => 'CC BY',
                'fullTextUrlList' => [
                    'fullTextUrl' => [[
                        'url' => 'https://europepmc.org/articles/PMC11260570?pdf=render',
                        'availability' => 'Open access',
                        'availabilityCode' => 'OA',
                        'documentStyle' => 'pdf',
                        'site' => 'Europe PMC',
                    ]],
                ],
            ]],
        ],
    ]));
    $limiter = new SpyRateLimiter();

    $candidate = europePmcSource($http, $limiter)->resolveCandidate(europePmcWork());

    expect($candidate?->url)->toBe('https://europepmc.org/articles/PMC11260570?pdf=render')
        ->and($candidate?->artifactType)->toBe(FullTextArtifactType::PDF)
        ->and($candidate?->metadata['source'])->toBe('europe_pmc')
        ->and($candidate?->metadata['availability_code'])->toBe('OA')
        ->and($candidate?->metadata['license'])->toBe('CC BY')
        ->and($http->calls[0]['query']['resultType'])->toBe('core')
        ->and($http->calls[0]['query']['format'])->toBe('json')
        ->and($http->calls[0]['timeout'])->toBe(15)
        ->and($limiter->waits)->toBe(1);
});

it('falls back to Europe PMC fullTextXML when no open PDF link is available', function (): void {
    $source = europePmcSource(new FakeHttpClient(new HttpResponse(200, [
        'resultList' => [
            'result' => [[
                'id' => '39040661',
                'source' => 'MED',
                'doi' => '10.5555/europe-pmc',
                'pmcid' => 'PMC11260570',
                'isOpenAccess' => 'Y',
                'fullTextIdList' => ['fullTextId' => ['PMC11260570']],
                'fullTextUrlList' => [
                    'fullTextUrl' => [[
                        'url' => 'https://publisher.example/article',
                        'availability' => 'Subscription required',
                        'documentStyle' => 'html',
                    ]],
                ],
            ]],
        ],
    ])));

    $candidate = $source->resolveCandidate(europePmcWork());

    expect($candidate?->artifactType)->toBe(FullTextArtifactType::XML)
        ->and($candidate?->url)->toBe('https://www.ebi.ac.uk/europepmc/webservices/rest/PMC11260570/fullTextXML')
        ->and($candidate?->metadata['artifact_source'])->toBe('fullTextXML');
});

it('skips Europe PMC results without open full-text links or XML signals', function (): void {
    $source = europePmcSource(new FakeHttpClient(new HttpResponse(200, [
        'resultList' => [
            'result' => [[
                'id' => '39040661',
                'source' => 'MED',
                'fullTextUrlList' => [
                    'fullTextUrl' => [[
                        'url' => 'https://publisher.example/article',
                        'availability' => 'Subscription required',
                        'documentStyle' => 'html',
                    ]],
                ],
            ]],
        ],
    ])));

    expect($source->resolveCandidate(europePmcWork()))->toBeNull();
});

