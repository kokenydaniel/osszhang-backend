<?php

namespace App\Services;

use App\Integrations\OpenAI\OpenAiChatClient;
use App\Models\Household;
use App\Support\AiUsageContext;

class OpenAIService
{
    public function __construct(
        private readonly OpenAiChatClient $client,
        private readonly AiTokenUsageService $tokenUsage,
        private readonly AiHouseholdPolicyService $householdPolicy,
    ) {}

    public function ask(string $prompt, array $context = [], ?AiUsageContext $usageContext = null): string
    {
        $this->assertHouseholdPolicy($usageContext);

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

        $result = $this->client->chat($messages);
        $this->tokenUsage->record($usageContext, $result['model'], $result['usage']);

        return $result['content'];
    }

    public function askJson(string $prompt, string $systemPrompt, ?AiUsageContext $usageContext = null): array
    {
        $this->assertHouseholdPolicy($usageContext);

        $result = $this->client->chatJson([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ]);

        $this->tokenUsage->record($usageContext, $result['model'], $result['usage']);

        return is_array($result['content']) ? $result['content'] : [];
    }

    private function assertHouseholdPolicy(?AiUsageContext $usageContext): void
    {
        if ($usageContext?->householdId === null) {
            return;
        }

        $household = Household::query()->find($usageContext->householdId);
        if ($household === null) {
            return;
        }

        $this->householdPolicy->assertCanUse($household);
    }
}
