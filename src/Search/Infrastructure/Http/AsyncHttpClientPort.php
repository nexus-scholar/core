<?php

declare(strict_types=1);

namespace Nexus\Search\Infrastructure\Http;

interface AsyncHttpClientPort
{
    /**
     * Start a GET request and return a package-owned pending response.
     *
     * @param  array<string,mixed>  $query
     * @param  array<string,string>  $headers
     */
    public function getAsync(
        string $url,
        array $query = [],
        array $headers = [],
        ?int $timeoutSeconds = null,
    ): PendingHttpResponse;
}
