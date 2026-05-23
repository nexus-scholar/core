<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Shared\Domain\ScholarlyWork;

final class DirectPdfSource implements FullTextSourcePort
{
    /** @var list<string> */
    private const KEYS = [
        'direct_pdf_url',
        'directPdfUrl',
        'pdf_url',
        'pdfUrl',
        'full_text_pdf_url',
        'fullTextPdfUrl',
    ];

    public function resolve(ScholarlyWork $work): ?string
    {
        $raw = $work->rawData();

        if ($raw === null) {
            return null;
        }

        return $this->firstUrlFrom($raw);
    }

    public function alias(): string
    {
        return 'direct';
    }

    public function supports(ScholarlyWork $work): bool
    {
        return $this->resolve($work) !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function firstUrlFrom(array $data): ?string
    {
        foreach (self::KEYS as $key) {
            $url = $this->validUrl($data[$key] ?? null);

            if ($url !== null) {
                return $url;
            }
        }

        foreach (['full_text', 'fullText', 'pdf', 'document'] as $key) {
            $nested = $data[$key] ?? null;

            if (! is_array($nested)) {
                continue;
            }

            $url = $this->firstUrlFrom($nested);

            if ($url !== null) {
                return $url;
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
