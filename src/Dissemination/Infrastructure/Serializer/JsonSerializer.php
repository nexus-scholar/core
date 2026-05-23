<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\Port\BibliographySerializerPort;
use Nexus\Search\Application\Dto\ScholarlyWorkDto;
use Nexus\Shared\Domain\CorpusSlice;

final class JsonSerializer implements BibliographySerializerPort
{
    public function serialize(CorpusSlice $corpus): string
    {
        $data = array_map(
            fn ($w) => ScholarlyWorkDto::fromDomain($w),
            $corpus->all()
        );

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function supports(BibliographyFormat $format): bool
    {
        return $format === BibliographyFormat::JSON;
    }
}
