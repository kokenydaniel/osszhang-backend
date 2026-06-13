<?php

namespace App\Integrations\OpenAI;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiChatClient
{
    /** @return array{content: string, model: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}} */
    public function chat(array $messages, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->defaultModel());
        $result = OpenAI::chat()->create(array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->defaultTemperature(),
        ], $options));

        return [
            'content' => (string) ($result->choices[0]->message->content ?? ''),
            'model' => (string) ($result->model ?? $model),
            'usage' => $this->normalizeUsage($result->usage ?? null),
        ];
    }

    /** @return array{content: array<string, mixed>, model: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}} */
    public function chatJson(array $messages, array $options = []): array
    {
        $model = (string) ($options['model'] ?? $this->defaultModel());
        $result = OpenAI::chat()->create(array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->defaultTemperature(),
            'response_format' => ['type' => 'json_object'],
        ], $options));

        $content = (string) ($result->choices[0]->message->content ?? '');

        return [
            'content' => json_decode($content, true) ?? [],
            'model' => (string) ($result->model ?? $model),
            'usage' => $this->normalizeUsage($result->usage ?? null),
        ];
    }

    private function defaultModel(): string
    {
        return (string) config('openai.model', 'gpt-4o-mini');
    }

    private function defaultTemperature(): float
    {
        return (float) config('openai.temperature', 0.7);
    }

    /** @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int, cached_tokens: int, reasoning_tokens: int} */
    private function normalizeUsage(mixed $usage): array
    {
        if ($usage === null) {
            return [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
                'cached_tokens' => 0,
                'reasoning_tokens' => 0,
            ];
        }

        $prompt = (int) ($usage->promptTokens ?? $usage->prompt_tokens ?? 0);
        $completion = (int) ($usage->completionTokens ?? $usage->completion_tokens ?? 0);
        $total = (int) ($usage->totalTokens ?? $usage->total_tokens ?? ($prompt + $completion));

        $promptDetails = $usage->promptTokensDetails ?? $usage->prompt_tokens_details ?? null;
        $completionDetails = $usage->completionTokensDetails ?? $usage->completion_tokens_details ?? null;

        $cached = 0;
        if (is_object($promptDetails)) {
            $cached = (int) ($promptDetails->cachedTokens ?? $promptDetails->cached_tokens ?? 0);
        } elseif (is_array($promptDetails)) {
            $cached = (int) ($promptDetails['cached_tokens'] ?? 0);
        }

        $reasoning = 0;
        if (is_object($completionDetails)) {
            $reasoning = (int) ($completionDetails->reasoningTokens ?? $completionDetails->reasoning_tokens ?? 0);
        } elseif (is_array($completionDetails)) {
            $reasoning = (int) ($completionDetails['reasoning_tokens'] ?? 0);
        }

        return [
            'prompt_tokens' => max(0, $prompt),
            'completion_tokens' => max(0, $completion),
            'total_tokens' => max(0, $total),
            'cached_tokens' => max(0, $cached),
            'reasoning_tokens' => max(0, $reasoning),
        ];
    }
}
