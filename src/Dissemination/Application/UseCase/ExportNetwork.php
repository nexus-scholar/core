<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Shared\Domain\CorpusSlice;

final readonly class ExportNetwork
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public CorpusSlice $corpus,
        public NetworkExportFormat $format,
        public string $filename,
        public ?string $projectId = null,
        public ?string $requestedBy = null,
        public array $metadata = [],
    ) {}
}
