<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class WorkIdentifierExtractor
{
    public static function doi(ScholarlyWork $work): ?string
    {
        $id = $work->ids()->findByNamespace(WorkIdNamespace::DOI);
        if ($id !== null) {
            return self::normalizeDoi($id->value);
        }

        $raw = $work->rawData();
        if ($raw === null) {
            return null;
        }

        return self::normalizeDoi(self::firstString($raw, [
            ['doi'],
            ['DOI'],
            ['ids', 'doi'],
            ['ids', 'DOI'],
            ['external_ids', 'doi'],
            ['external_ids', 'DOI'],
            ['externalIds', 'doi'],
            ['externalIds', 'DOI'],
        ]));
    }

    public static function pmcid(ScholarlyWork $work): ?string
    {
        $id = $work->ids()->findByNamespace(WorkIdNamespace::PMCID);
        if ($id !== null) {
            return self::normalizePmcid($id->value);
        }

        $raw = $work->rawData();
        if ($raw === null) {
            return null;
        }

        return self::normalizePmcid(self::firstString($raw, [
            ['pmcid'],
            ['pmc_id'],
            ['pmcId'],
            ['PMCID'],
            ['ids', 'pmcid'],
            ['ids', 'pmc'],
            ['external_ids', 'PMCID'],
            ['external_ids', 'pmcid'],
            ['externalIds', 'PMCID'],
            ['externalIds', 'PubMedCentral'],
            ['fullTextIdList', 'fullTextId'],
        ]));
    }

    public static function pmcidNumber(ScholarlyWork $work): ?string
    {
        $pmcid = self::pmcid($work);

        return $pmcid === null ? null : substr($pmcid, 3);
    }

    public static function normalizeDoi(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $doi = trim((string) $value);
        if ($doi === '') {
            return null;
        }

        $doi = preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $doi) ?? $doi;
        $doi = preg_replace('#^doi:\s*#i', '', $doi) ?? $doi;
        $doi = trim($doi);

        return preg_match('#^10\.\S+/.+#', $doi) === 1 ? $doi : null;
    }

    public static function normalizePmcid(mixed $value): ?string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $pmcid = self::normalizePmcid($item);
                if ($pmcid !== null) {
                    return $pmcid;
                }
            }

            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $candidate = trim((string) $value);
        if ($candidate === '') {
            return null;
        }

        if (preg_match('/PMC\s*([0-9]+)/i', $candidate, $matches) === 1) {
            return 'PMC'.$matches[1];
        }

        if (ctype_digit($candidate)) {
            return 'PMC'.$candidate;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<list<string>>  $paths
     */
    private static function firstString(array $data, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = self::valueAt($data, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private static function valueAt(array $data, array $path): mixed
    {
        $value = $data;

        foreach ($path as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }
}
