<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Domain\Exception;

use DomainException;
use Nexus\Shared\ValueObject\WorkId;

final class WorkNotInGraph extends DomainException
{
    public function __construct(WorkId $workId)
    {
        parent::__construct(sprintf('Work "%s" is not part of the citation graph.', $workId->toString()));
    }
}
