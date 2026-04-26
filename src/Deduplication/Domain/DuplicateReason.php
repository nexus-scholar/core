<?php

declare(strict_types=1);

namespace Nexus\Deduplication\Domain;

enum DuplicateReason: string
{
    case DOI_MATCH      = 'doi_match';
    case ARXIV_MATCH    = 'arxiv_match';
    case OPENALEX_MATCH = 'openalex_match';
    case S2_MATCH       = 's2_match';
    case PUBMED_MATCH   = 'pubmed_match';
    case TITLE_FUZZY    = 'title_fuzzy';
    case FINGERPRINT    = 'fingerprint';
}
