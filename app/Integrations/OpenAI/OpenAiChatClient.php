<?php

namespace App\Integrations\OpenAI;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiChatClient
{
    public function chat(array $messages, array $options = []): string
    {
        $result = OpenAI::chat()->create(array_merge([
            'model' => $this->defaultModel(),
            'messages' => $messages,
            'temperature' => $this->defaultTemperature(),
        ], $options));

        return $result->choices[0]->message->content;
    }

    public function chatJson(array $messages, array $options = []): array
    {
        $result = OpenAI::chat()->create(array_merge([
            'model' => $this->defaultModel(),
            'messages' => $messages,
            'temperature' => $this->defaultTemperature(),
            'response_format' => ['type' => 'json_object'],
        ], $options));

        $content = $result->choices[0]->message->content;

        return json_decode($content, true) ?? [];
    }

    private function defaultModel(): string
    {
        return (string) config('openai.model', 'gpt-4o-mini');
    }

    private function defaultTemperature(): float
    {
        return (float) config('openai.temperature', 0.7);
    }
}
