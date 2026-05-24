<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Http;

use Nexus\Search\Domain\Port\HttpResponse;

interface PendingHttpResponse
{
    public function wait(): HttpResponse;
}
