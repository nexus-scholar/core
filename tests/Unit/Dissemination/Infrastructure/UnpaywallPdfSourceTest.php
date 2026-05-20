<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\PdfSource\FullTextSourceConfig;
use Nexus\Dissemination\Infrastructure\PdfSource\OaHttpClient;
use Nexus\Dissemination\Infrastructure\PdfSource\UnpaywallPdfSource;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;
use Tests\Support\FakeHttpClient;
use Tests\Support\SpyRateLimiter;

function unpaywallWork(string $doi = '10.5555/example'): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, $doi)]),
        title: 'Unpaywall Test Work',
        sourceProvider: 'fixture',
    );
}

function unpaywallSource(FakeHttpClient $http, ?SpyRateLimiter $limiter = null, ?string $email = 'dev@example.com'): UnpaywallPdfSource
{
    return new UnpaywallPdfSource(
        new OaHttpClient(
            $http,
            $limiter ?? new SpyRateLimiter(),
            FullTextSourceConfig::fromArray(
                'unpaywall',
                'https://api.unpaywall.org/v2',
                ['email' => $email, 'timeout' => 11, 'max_retries' => 2],
            ),
        ),
    );
}

it('resolves a DOI to the best Unpaywall PDF URL and preserves OA metadata', function (): void {
    $http = new FakeHttpClient(new HttpResponse(200, [
        'doi' => '10.5555/example',
        'is_oa' => true,
        'oa_status' => 'gold',
        'best_oa_location' => [
            'url_for_pdf' => 'https://repository.example/paper.pdf',
            'license' => 'cc-by',
            'host_type' => 'repository',
            'version' => 'publishedVersion',
            'url_for_landing_page' => 'https://repository.example/paper',
        ],
    ]));
    $limiter = new SpyRateLimiter();

    $candidate = unpaywallSource($http, $limiter)->resolveCandidate(unpaywallWork());

    expect($candidate?->url)->toBe('https://repository.example/paper.pdf')
        ->and($candidate?->metadata['oa_status'])->toBe('gold')
        ->and($candidate?->metadata['license'])->toBe('cc-by')
        ->and($candidate?->metadata['host_type'])->toBe('repository')
        ->and($http->calls[0]['timeout'])->toBe(11)
        ->and($http->calls[0]['query']['email'])->toBe('dev@example.com')
        ->and($limiter->waits)->toBe(1);
});

it('falls back to another OA location when best location has no PDF', function (): void {
    $source = unpaywallSource(new FakeHttpClient(new HttpResponse(200, [
        'doi' => '10.5555/fallback',
        'is_oa' => true,
        'oa_status' => 'green',
        'best_oa_location' => [
            'url_for_landing_page' => 'https://repository.example/landing',
        ],
        'oa_locations' => [
            [
                'url_for_pdf' => 'https://repository.example/fallback.pdf',
                'license' => 'cc-by-nc',
            ],
        ],
    ])));

    $candidate = $source->resolveCandidate(unpaywallWork('10.5555/fallback'));

    expect($candidate?->url)->toBe('https://repository.example/fallback.pdf')
        ->and($candidate?->metadata['license'])->toBe('cc-by-nc');
});

it('skips closed, non-OA, and no-PDF Unpaywall results', function (): void {
    $closed = unpaywallSource(new FakeHttpClient(new HttpResponse(200, [
        'is_oa' => true,
        'oa_status' => 'closed',
        'best_oa_location' => ['url_for_pdf' => 'https://example.org/closed.pdf'],
    ])));

    $notOpen = unpaywallSource(new FakeHttpClient(new HttpResponse(200, [
        'is_oa' => false,
        'best_oa_location' => ['url_for_pdf' => 'https://example.org/not-open.pdf'],
    ])));

    $noPdf = unpaywallSource(new FakeHttpClient(new HttpResponse(200, [
        'is_oa' => true,
        'oa_status' => 'bronze',
        'best_oa_location' => ['url_for_landing_page' => 'https://example.org/article'],
    ])));

    expect($closed->resolveCandidate(unpaywallWork()))->toBeNull()
        ->and($notOpen->resolveCandidate(unpaywallWork()))->toBeNull()
        ->and($noPdf->resolveCandidate(unpaywallWork()))->toBeNull();
});

it('rejects missing Unpaywall email without making HTTP calls', function (): void {
    $http = new FakeHttpClient(new HttpResponse(200, []));
    $source = unpaywallSource($http, email: null);

    expect($source->supports(unpaywallWork()))->toBeFalse()
        ->and($source->resolveCandidate(unpaywallWork()))->toBeNull()
        ->and($http->calls)->toBe([]);
});

