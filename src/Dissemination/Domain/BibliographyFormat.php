<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain;

enum BibliographyFormat: string
{
    case BIBTEX = 'bibtex';
    case RIS    = 'ris';
    case CSV    = 'csv';
    case JSON   = 'json';
    case JSONL  = 'jsonl';

    public function extension(): string
    {
        return match ($this) {
            self::BIBTEX => 'bib',
            self::RIS    => 'ris',
            self::CSV    => 'csv',
            self::JSON   => 'json',
            self::JSONL  => 'jsonl',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::BIBTEX => 'application/x-bibtex',
            self::RIS    => 'application/x-research-info-systems',
            self::CSV    => 'text/csv',
            self::JSON   => 'application/json',
            self::JSONL  => 'application/x-jsonlines',
        };
    }
}
