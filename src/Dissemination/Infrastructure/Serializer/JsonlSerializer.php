<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Shared\Domain\CorpusSlice;

final class JsonlSerializer implements BibliographySerializerPort
{
    public function serialize(CorpusSlice $corpus): string
    {
        $lines = [];
        foreach ($corpus->all() as $work) {
            $lines[] = json_encode(
                ScholarlyWorkDto::fromDomain($work),
                JSON_UNESCAPED_SLASHES
            );
        }

        return implode("\n", $lines);
    }

    public function supports(BibliographyFormat $format): bool
    {
        return $format === BibliographyFormat::JSONL;
    }
}
