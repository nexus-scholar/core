<?php

declare(strict_types=1);

use Nexus\Dissemination\Domain\FullTextArtifactType;
use Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig;
use Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient;
use Nexus\Dissemination\Infrastructure\PdfSource\PmcOaiFullTextSource;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Tests\Support\FakeHttpClient;
use Tests\Support\SpyRateLimiter;

function pmcWork(?array $rawData = ['pmcid' => 'PMC12124693']): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.5555/pmc')]),
        title: 'PMC Test Work',
        sourceProvider: 'fixture',
        rawData: $rawData,
    );
}

function pmcSource(FakeHttpClient $http, ?SpyRateLimiter $limiter = null): PmcOaiFullTextSource
{
    return new PmcOaiFullTextSource(
        new OaHttpClient(
            $http,
            $limiter ?? new SpyRateLimiter(3.0),
            FullTextSourceConfig::fromArray(
                'pmc',
                'https://pmc.ncbi.nlm.nih.gov/api/oai/v1/mh',
                ['timeout' => 15, 'rate_limit' => 3.0, 'prefer_xml' => true],
                ['prefer_pdf' => false],
            ),
        ),
    );
}

it('resolves a PMCID to a reusable PMC OAI full-text XML candidate', function (): void {
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<OAI-PMH>
  <GetRecord>
    <record>
      <metadata>
        <article>
          <front>
            <permissions><license xlink:href="https://creativecommons.org/licenses/by/4.0/" /></permissions>
          </front>
        </article>
      </metadata>
    </record>
  </GetRecord>
</OAI-PMH>
XML;

    $http = new FakeHttpClient(new HttpResponse(200, [], $xml));
    $limiter = new SpyRateLimiter(3.0);

    $candidate = pmcSource($http, $limiter)->resolveCandidate(pmcWork());

    expect($candidate?->artifactType)->toBe(FullTextArtifactType::XML)
        ->and($candidate?->url)->toContain('verb=GetRecord')
        ->and($candidate?->url)->toContain('metadataPrefix=pmc')
        ->and($candidate?->metadata['pmcid'])->toBe('PMC12124693')
        ->and($candidate?->metadata['license'])->toBe('https://creativecommons.org/licenses/by/4.0/')
        ->and($http->calls[0]['query']['identifier'])->toBe('oai:pubmedcentral.nih.gov:12124693')
        ->and($http->calls[0]['timeout'])->toBe(15)
        ->and($limiter->waits)->toBe(1);
});

it('does not expose a PMC XML candidate for OAI errors or missing PMCID', function (): void {
    $source = pmcSource(new FakeHttpClient(new HttpResponse(200, [], '<OAI-PMH><error code="idDoesNotExist" /></OAI-PMH>')));

    expect($source->resolveCandidate(pmcWork()))->toBeNull()
        ->and($source->supports(pmcWork(null)))->toBeFalse()
        ->and($source->resolveCandidate(pmcWork(null)))->toBeNull();
});
