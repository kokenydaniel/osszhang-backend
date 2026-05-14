<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    /**
     * Analyze financial data or respond to queries.
     */
    public function ask(string $prompt, array $context = [])
    {
        $systemPrompt = "You are PénzPilot AI, a professional financial assistant for a Hungarian family. 
        Analyze the provided data and give concise, helpful advice in Hungarian. 
        Always consider the budget, utility bills, and business performance.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt]
        ];

        if (!empty($context)) {
            $messages[] = ['role' => 'assistant', 'content' => "Current Context: " . json_encode($context)];
        }

        $result = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        return $result->choices[0]->message->content;
    }

    /**
     * Specifically parse transaction descriptions (e.g., from bank imports).
     */
    public function parseTransaction(string $description)
    {
        $prompt = "Parse this transaction description and return a JSON object with 'category', 'description' (clean), and 'is_budget' (boolean). 
        Description: \"$description\"";

        $result = OpenAI::chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => "You are a data parser. Return ONLY valid JSON."],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object']
        ]);

        return json_decode($result->choices[0]->message->content, true);
    }
}
