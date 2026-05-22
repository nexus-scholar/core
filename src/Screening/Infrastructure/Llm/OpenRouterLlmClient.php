<?php

declare(strict_types=1);

namespace Nexus\Screening\Infrastructure\Llm;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Nexus\Screening\Application\Llm\LlmRequest;
use Nexus\Screening\Application\Llm\LlmResponse;
use Nexus\Screening\Application\Port\LlmClientPort;

final readonly class OpenRouterLlmClient implements LlmClientPort
{
    public function __construct(
        private ClientInterface $http,
        private string $apiKey,
        private string $baseUrl = 'https://openrouter.ai/api/v1',
        private int $timeoutSeconds = 45,
        private ?string $referer = null,
        private ?string $appName = 'Nexus Scholar',
    ) {
        if (trim($apiKey) === '') {
            throw new LlmClientException('OpenRouter API key is required.', 'openrouter');
        }
    }

    public function completeJson(LlmRequest $request): LlmResponse
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->http->request(
                'POST',
                rtrim($this->baseUrl, '/').'/chat/completions',
                [
                    'headers' => $this->headers(),
                    'json' => $this->payload($request),
                    'timeout' => $this->timeoutSeconds,
                    'http_errors' => false,
                ],
            );
        } catch (GuzzleException $error) {
            throw new LlmClientException(
                message: $error->getMessage(),
                provider: 'openrouter',
                model: $request->model,
                previous: $error,
            );
        }

        $rawBody = (string) $response->getBody();
        $body = $this->decodeBody($rawBody, $request);
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300 || isset($body['error'])) {
            throw new LlmClientException(
                message: $this->errorMessage($body, $statusCode),
                provider: 'openrouter',
                model: $request->model,
                statusCode: $statusCode,
            );
        }

        $content = $this->messageContent($body, $request);

        return new LlmResponse(
            provider: 'openrouter',
            model: is_string($body['model'] ?? null) ? $body['model'] : $request->model,
            content: $content,
            rawResponse: $rawBody,
            usage: is_array($body['usage'] ?? null) ? $body['usage'] : [],
            latencyMs: (int) round((hrtime(true) - $startedAt) / 1_000_000),
        );
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->referer !== null && trim($this->referer) !== '') {
            $headers['HTTP-Referer'] = $this->referer;
        }

        if ($this->appName !== null && trim($this->appName) !== '') {
            $headers['X-Title'] = $this->appName;
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LlmRequest $request): array
    {
        return [
            'model' => $request->model,
            'messages' => $request->messages,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'screening_decision',
                    'strict' => true,
                    'schema' => $request->responseSchema,
                ],
            ],
            'provider' => [
                'require_parameters' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $rawBody, LlmRequest $request): array
    {
        if (trim($rawBody) === '') {
            throw new LlmClientException('OpenRouter returned an empty response body.', 'openrouter', $request->model);
        }

        $decoded = json_decode($rawBody, true);

        if (! is_array($decoded)) {
            throw new LlmClientException('OpenRouter returned a non-JSON response body.', 'openrouter', $request->model);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function messageContent(array $body, LlmRequest $request): array
    {
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (is_array($content)) {
            return $content;
        }

        if (! is_string($content) || trim($content) === '') {
            throw new LlmClientException('OpenRouter response did not contain message content.', 'openrouter', $request->model);
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new LlmClientException('OpenRouter message content was not valid JSON.', 'openrouter', $request->model);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, int $statusCode): string
    {
        $message = $body['error']['message'] ?? null;

        if (is_scalar($message) && trim((string) $message) !== '') {
            return (string) $message;
        }

        return "OpenRouter request failed with status {$statusCode}.";
    }
}
