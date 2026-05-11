<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Search\Domain\CorpusSlice;

final readonly class ExportNetwork
{
    public function __construct(
        public CorpusSlice         $corpus,
        public NetworkExportFormat $format,
        public string              $filename,
    ) {}
}
