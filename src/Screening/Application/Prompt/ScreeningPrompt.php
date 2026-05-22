<?php

declare(strict_types=1);

namespace Nexus\Screening\Application\Prompt;

final readonly class ScreeningPrompt
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $responseSchema
     */
    public function __construct(
        public array $messages,
        public array $responseSchema,
        ?string $hash = null,
    ) {
        if ($messages === []) {
            throw new \InvalidArgumentException('Screening prompt messages must not be empty.');
        }

        $this->hash = $hash ?? $this->computeHash($messages, $responseSchema);
    }

    public string $hash;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $responseSchema
     */
    private function computeHash(array $messages, array $responseSchema): string
    {
        return hash('sha256', json_encode([
            'messages' => $messages,
            'response_schema' => $responseSchema,
        ], JSON_THROW_ON_ERROR));
    }

    public function text(): string
    {
        return implode("\n\n", array_map(
            static fn (array $message): string => strtoupper($message['role']).":\n".$message['content'],
            $this->messages,
        ));
    }
}
