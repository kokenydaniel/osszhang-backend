<?php

namespace App\Services;

use App\Integrations\OpenAI\OpenAiChatClient;

class OpenAIService
{
    public function __construct(
        private readonly OpenAiChatClient $client,
    ) {}

    public function ask(string $prompt, array $context = []): string
    {
        $systemPrompt = 'You are Összhang AI, a professional financial assistant for a Hungarian family.
        Analyze the provided data and give concise, helpful advice in Hungarian. 
        Always consider the budget, utility bills, and business performance.';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        if (! empty($context)) {
            $messages[] = ['role' => 'assistant', 'content' => 'Current Context: '.json_encode($context)];
        }

        return $this->client->chat($messages);
    }

    /** @return array<string, mixed> */
    public function askJson(string $prompt, string $systemPrompt): array
    {
        return $this->client->chatJson([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ]);
    }
}
