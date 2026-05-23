<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Shared\Domain\ScholarlyWork;

final class SemanticScholarPdfSource implements FullTextSourcePort
{
    public function resolve(ScholarlyWork $work): ?string
    {
        $raw = $work->rawData();
        if ($raw === null) {
            return null;
        }

        // Semantic Scholar structure for openAccessPdf
        return $raw['openAccessPdf']['url'] ?? null;
    }

    public function alias(): string
    {
        return 'semantic_scholar';
    }

    public function supports(ScholarlyWork $work): bool
    {
        $raw = $work->rawData();
        if ($raw === null) {
            return false;
        }

        return isset($raw['openAccessPdf']['url']);
    }
}
