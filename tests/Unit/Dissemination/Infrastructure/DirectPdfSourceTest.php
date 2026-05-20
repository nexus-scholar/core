<?php

declare(strict_types=1);

use Nexus\Dissemination\Infrastructure\PdfSource\DirectPdfSource;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;
use Nexus\Shared\ValueObject\WorkIdSet;

function workWithRawPdfData(?array $rawData): ScholarlyWork
{
    return ScholarlyWork::reconstitute(
        ids: WorkIdSet::fromArray([new WorkId(WorkIdNamespace::DOI, '10.5555/direct-pdf')]),
        title: 'Direct PDF Source Work',
        sourceProvider: 'fixture',
        rawData: $rawData,
    );
}

it('resolves explicit direct pdf urls from raw provider metadata', function (): void {
    $source = new DirectPdfSource();
    $work = workWithRawPdfData([
        'direct_pdf_url' => 'https://example.org/papers/direct.pdf',
    ]);

    expect($source->alias())->toBe('direct')
        ->and($source->supports($work))->toBeTrue()
        ->and($source->resolve($work))->toBe('https://example.org/papers/direct.pdf');
});

it('resolves nested full text pdf urls from raw provider metadata', function (): void {
    $source = new DirectPdfSource();
    $work = workWithRawPdfData([
        'full_text' => [
            'pdf_url' => 'https://repository.example/download?id=123',
        ],
    ]);

    expect($source->supports($work))->toBeTrue()
        ->and($source->resolve($work))->toBe('https://repository.example/download?id=123');
});

it('ignores generic landing page urls and invalid schemes', function (): void {
    $source = new DirectPdfSource();

    expect($source->supports(workWithRawPdfData(['url' => 'https://example.org/article'])))->toBeFalse()
        ->and($source->supports(workWithRawPdfData(['pdf_url' => 'ftp://example.org/paper.pdf'])))->toBeFalse()
        ->and($source->supports(workWithRawPdfData(null)))->toBeFalse();
});
