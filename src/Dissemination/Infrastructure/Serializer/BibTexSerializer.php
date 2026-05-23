<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class BibTexSerializer implements BibliographySerializerPort
{
    public function serialize(CorpusSlice $corpus): string
    {
        $entries = [];
        foreach ($corpus->all() as $work) {
            $entries[] = $this->serializeWork($work);
        }

        return implode("\n\n", $entries);
    }

    public function supports(BibliographyFormat $format): bool
    {
        return $format === BibliographyFormat::BIBTEX;
    }

    private function serializeWork(ScholarlyWork $work): string
    {
        $id = $work->primaryId()?->toString() ?? 'work_'.spl_object_hash($work);
        $type = $work->isPreprint() ? 'misc' : 'article';

        $authors = array_map(fn ($a) => $a->fullName(), $work->authors()->all());
        $authorString = implode(' and ', $authors);

        $lines = [
            sprintf('@%s{%s,', $type, $id),
            sprintf('  title = {%s},', $work->title()),
            sprintf('  author = {%s},', $authorString),
        ];

        if ($work->year() !== null) {
            $lines[] = sprintf('  year = {%d},', $work->year());
        }

        if ($work->venue() !== null) {
            $venueField = $type === 'article' ? 'journal' : 'note';
            $lines[] = sprintf('  %s = {%s},', $venueField, $work->venue()->name);
        }

        $doi = $work->ids()->findByNamespace(WorkIdNamespace::DOI);
        if ($doi !== null) {
            $lines[] = sprintf('  doi = {%s},', $doi->toString());
        }

        // Remove trailing comma from last field
        $lastIndex = count($lines) - 1;
        $lines[$lastIndex] = rtrim($lines[$lastIndex], ',');

        $lines[] = '}';

        return implode("\n", $lines);
    }
}
