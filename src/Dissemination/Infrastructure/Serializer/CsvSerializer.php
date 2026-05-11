<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Search\Domain\CorpusSlice;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class CsvSerializer implements BibliographySerializerPort
{
    public function serialize(CorpusSlice $corpus): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // Header
        fputcsv($handle, [
            'ID',
            'Title',
            'Authors',
            'Year',
            'Venue',
            'DOI',
            'Source',
            'Cited By',
        ]);

        foreach ($corpus->all() as $work) {
            $authors = array_map(fn ($a) => $a->fullName(), $work->authors()->all());

            fputcsv($handle, [
                $work->primaryId()?->toString() ?? '',
                $work->title(),
                implode('; ', $authors),
                (string) ($work->year() ?? ''),
                $work->venue()?->name ?? '',
                $work->ids()->findByNamespace(WorkIdNamespace::DOI)?->toString() ?? '',
                $work->sourceProvider(),
                (string) ($work->citedByCount() ?? 0),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    public function supports(BibliographyFormat $format): bool
    {
        return $format === BibliographyFormat::CSV;
    }
}
