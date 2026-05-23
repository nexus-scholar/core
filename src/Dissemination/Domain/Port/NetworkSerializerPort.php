<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Domain\Port;

use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Shared\Domain\CorpusSlice;

interface NetworkSerializerPort
{
    /**
     * For now, we assume we might want to export a whole slice,
     * although network export usually involves a Graph object.
     * We'll keep it flexible for now.
     */
    public function serialize(CorpusSlice $corpus): string;

    public function supports(NetworkExportFormat $format): bool;
}
