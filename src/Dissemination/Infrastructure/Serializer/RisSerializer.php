<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Shared\Domain\CorpusSlice;
use Nexus\Shared\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class RisSerializer implements BibliographySerializerPort
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
        return $format === BibliographyFormat::RIS;
    }

    private function serializeWork(ScholarlyWork $work): string
    {
        $lines = [];
        $lines[] = sprintf('TY  - %s', $work->isPreprint() ? 'GEN' : 'JOUR');
        $lines[] = sprintf('TI  - %s', $work->title());

        foreach ($work->authors()->all() as $author) {
            $lines[] = sprintf('AU  - %s', $author->fullName());
        }

        if ($work->year() !== null) {
            $lines[] = sprintf('PY  - %d', $work->year());
        }

        if ($work->venue() !== null) {
            $lines[] = sprintf('JO  - %s', $work->venue()->name);
        }

        $doi = $work->ids()->findByNamespace(WorkIdNamespace::DOI);
        if ($doi !== null) {
            $lines[] = sprintf('DO  - %s', $doi->value);
        }

        $lines[] = 'ER  -';

        return implode("\n", $lines);
    }
}
