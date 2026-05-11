<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Application\UseCase;

use Nexus\Search\Domain\ScholarlyWork;

final readonly class RetrieveFullText
{
    public function __construct(
        public ScholarlyWork $work,
        public string        $destinationFolder = 'pdfs',
    ) {}
}
