<?php

declare(strict_types=1);

namespace Nexus\CitationNetwork\Application\Exception;

use RuntimeException;
use Nexus\CitationNetwork\Domain\CitationGraphId;

final class CitationGraphNotFound extends RuntimeException
{
    public function __construct(CitationGraphId $id)
    {
        parent::__construct(sprintf('Citation graph "%s" was not found.', $id->toString()));
    }
}
