<?php

namespace App\Integrations\OpenAI;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiChatClient
{
    public function chat(array $messages, array $options = []): string
    {
        $result = OpenAI::chat()->create(array_merge([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
        ], $options));

        return $result->choices[0]->message->content;
    }

    public function chatJson(array $messages, string $model = 'gpt-3.5-turbo'): array
    {
        $result = OpenAI::chat()->create([
            'model' => $model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
        ]);

        return json_decode($result->choices[0]->message->content, true);
    }
}
