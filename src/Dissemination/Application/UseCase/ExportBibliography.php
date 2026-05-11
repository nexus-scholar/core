<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Search\Domain\CorpusSlice;

final readonly class ExportBibliography
{
    public function __construct(
        public CorpusSlice        $corpus,
        public BibliographyFormat $format,
        public string             $filename,
    ) {}
}
