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

        return $this->firstPdfUrl($raw);
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

        return $this->firstPdfUrl($raw) !== null;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function firstPdfUrl(array $raw): ?string
    {
        foreach ([
            $raw['best_oa_location']['pdf_url'] ?? null,
            $raw['primary_location']['pdf_url'] ?? null,
        ] as $url) {
            $valid = $this->validUrl($url);
            if ($valid !== null) {
                return $valid;
            }
        }

        foreach (['locations', 'oa_locations'] as $key) {
            $locations = $raw[$key] ?? null;
            if (! is_array($locations)) {
                continue;
            }

            foreach ($locations as $location) {
                if (! is_array($location)) {
                    continue;
                }

                $valid = $this->validUrl($location['pdf_url'] ?? null);
                if ($valid !== null) {
                    return $valid;
                }
            }
        }

        return null;
    }

    private function validUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
