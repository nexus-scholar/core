<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Shared\Domain\CorpusSlice;

interface BibliographySerializerPort
{
    public function serialize(CorpusSlice $corpus): string;

    public function supports(BibliographyFormat $format): bool;
}
