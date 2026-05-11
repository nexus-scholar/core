<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\PdfSource;

use Nexus\Dissemination\Domain\Port\FullTextSourcePort;
use Nexus\Search\Domain\ScholarlyWork;
use Nexus\Shared\ValueObject\WorkIdNamespace;

final class ArXivPdfSource implements FullTextSourcePort
{
    public function resolve(ScholarlyWork $work): ?string
    {
        $id = $work->ids()->findByNamespace(WorkIdNamespace::ARXIV);
        if ($id === null) {
            return null;
        }

        return sprintf('https://arxiv.org/pdf/%s.pdf', $id->value);
    }

    public function alias(): string
    {
        return 'arxiv';
    }

    public function supports(ScholarlyWork $work): bool
    {
        return $work->ids()->findByNamespace(WorkIdNamespace::ARXIV) !== null;
    }
}
