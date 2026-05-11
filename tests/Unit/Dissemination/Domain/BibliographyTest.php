<?php

declare(strict_types=1);

use Nexus\Dissemination\Domain\Bibliography;
use Nexus\Dissemination\Domain\BibliographyFormat;

it('creates_bibliography_with_default_timestamp', function (): void {
    $bib = Bibliography::create(
        format: BibliographyFormat::BIBTEX,
        content: 'content',
        filename: 'exports/test.bib'
    );

    expect($bib->format)->toBe(BibliographyFormat::BIBTEX);
    expect($bib->content)->toBe('content');
    expect($bib->filename)->toBe('exports/test.bib');
    expect($bib->generatedAt)->toBeInstanceOf(DateTimeImmutable::class);
    expect($bib->sizeBytes())->toBe(7);
});

