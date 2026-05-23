<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Infrastructure\Llm\LlmClientException;
use Nexus\Screening\Infrastructure\Llm\OpenRouterLlmClient;

it('sends schema-bound chat completions requests and parses json model content', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'decision' => 'include',
                            'confidence' => 0.92,
                            'reason' => 'Direct match.',
                            'evidence' => ['tomato instance segmentation'],
                            'uncertainty' => [],
                            'exclusion_basis' => [],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 40],
        ], JSON_THROW_ON_ERROR)),
    ]));
    $stack->push(Middleware::history($history));

    $client = new OpenRouterLlmClient(
        http: new Client(['handler' => $stack]),
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeoutSeconds: 12,
    );

    $response = $client->completeJson(new LlmRequest(
        model: 'openai/gpt-4.1-mini',
        messages: [['role' => 'user', 'content' => 'Screen this work.']],
        responseSchema: [
            'type' => 'object',
            'properties' => ['decision' => ['type' => 'string']],
            'required' => ['decision'],
        ],
        temperature: 0.0,
        maxTokens: 600,
    ));

    $request = $history[0]['request'];
    $payload = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

    expect($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($payload['model'])->toBe('openai/gpt-4.1-mini')
        ->and($payload['response_format']['type'])->toBe('json_schema')
        ->and($payload['response_format']['json_schema']['strict'])->toBeTrue()
        ->and($payload['provider']['require_parameters'])->toBeTrue()
        ->and($response->content['decision'])->toBe('include')
        ->and($response->usage['completion_tokens'])->toBe(40)
        ->and($response->rawResponse)->toContain('choices');
});

it('raises a typed llm error for openrouter error responses', function (): void {
    $client = new OpenRouterLlmClient(
        http: new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(402, ['Content-Type' => 'application/json'], json_encode([
                    'error' => ['code' => 402, 'message' => 'Insufficient credits.'],
                ], JSON_THROW_ON_ERROR)),
            ])),
        ]),
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeoutSeconds: 12,
    );

    expect(fn () => $client->completeJson(new LlmRequest(
        model: 'openai/gpt-4.1-mini',
        messages: [['role' => 'user', 'content' => 'Screen this work.']],
        responseSchema: ['type' => 'object'],
    )))->toThrow(LlmClientException::class, 'Insufficient credits.');
});

it('retries transient openrouter failures before returning a valid response', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(429, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => 'Rate limited.'],
        ], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'decision' => 'include',
                            'confidence' => 0.81,
                            'reason' => 'Recovered after transient rate limit.',
                            'evidence' => [],
                            'uncertainty' => [],
                            'exclusion_basis' => [],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR)),
    ]));
    $stack->push(Middleware::history($history));

    $client = new OpenRouterLlmClient(
        http: new Client(['handler' => $stack]),
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeoutSeconds: 12,
        maxRetries: 2,
        initialRetryDelayMs: 1,
        sleeper: static fn (int $milliseconds): null => null,
    );

    $response = $client->completeJson(new LlmRequest(
        model: 'openai/gpt-4.1-mini',
        messages: [['role' => 'user', 'content' => 'Screen this work.']],
        responseSchema: ['type' => 'object'],
    ));

    expect($history)->toHaveCount(2)
        ->and($response->content['decision'])->toBe('include');
});

it('does not retry non-transient openrouter failures', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(402, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => 'Insufficient credits.'],
        ], JSON_THROW_ON_ERROR)),
    ]));
    $stack->push(Middleware::history($history));

    $client = new OpenRouterLlmClient(
        http: new Client(['handler' => $stack]),
        apiKey: 'test-key',
        baseUrl: 'https://openrouter.ai/api/v1',
        timeoutSeconds: 12,
        maxRetries: 3,
        initialRetryDelayMs: 1,
        sleeper: static fn (int $milliseconds): null => null,
    );

    expect(fn () => $client->completeJson(new LlmRequest(
        model: 'openai/gpt-4.1-mini',
        messages: [['role' => 'user', 'content' => 'Screen this work.']],
        responseSchema: ['type' => 'object'],
    )))->toThrow(LlmClientException::class, 'Insufficient credits.');

    expect($history)->toHaveCount(1);
});
