<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Search\Domain\ScholarlyWork;

final class OpenAlexPdfSource implements FullTextSourcePort
{
    public function resolve(ScholarlyWork $work): ?string
    {
        $raw = $work->rawData();
        if ($raw === null) {
            return null;
        }

        // OpenAlex structure for best_oa_location
        return $raw['best_oa_location']['pdf_url'] 
            ?? $raw['open_access']['oa_url'] 
            ?? null;
    }

    public function alias(): string
    {
        return 'openalex';
    }

    public function supports(ScholarlyWork $work): bool
    {
        $raw = $work->rawData();
        if ($raw === null) {
            return false;
        }

        return isset($raw['best_oa_location']['pdf_url']) 
            || isset($raw['open_access']['oa_url']);
    }
}
