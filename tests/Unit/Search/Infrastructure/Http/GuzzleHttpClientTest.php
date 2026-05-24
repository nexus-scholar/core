<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Nexus\Search\Infrastructure\Http\AsyncHttpClientPort;
use Nexus\Search\Infrastructure\Http\GuzzleHttpClient;

it('maps async guzzle responses behind the package owned http contract', function (): void {
    $mock = new MockHandler([
        new Response(200, ['X-Test' => 'yes'], '{"ok":true}'),
    ]);

    $client = new GuzzleHttpClient(new Client([
        'handler' => HandlerStack::create($mock),
    ]));

    expect($client)->toBeInstanceOf(AsyncHttpClientPort::class);

    $response = $client->getAsync('https://example.test/search', ['q' => 'tomato'])->wait();

    expect($response->statusCode)->toBe(200)
        ->and($response->body)->toBe(['ok' => true])
        ->and($response->rawBody)->toBe('{"ok":true}')
        ->and($response->header('x-test'))->toBe('yes');
});
