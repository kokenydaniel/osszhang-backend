<?php

namespace App\Console\Commands;

use App\Models\AiTokenUsageEvent;
use App\Support\AiTokenPricing;
use Illuminate\Console\Command;

class BackfillAiUsageCostsCommand extends Command
{
    protected $signature = 'ai:backfill-usage-costs {--force : Update rows that already have cost_usd}';

    protected $description = 'Backfill stored cost_usd on AI usage events using OpenAI Standard tier pricing';

    public function handle(): int
    {
        if (! AiTokenPricing::isConfigured()) {
            $this->error('No OpenAI model pricing configured (see config/openai_model_pricing.php).');

            return self::FAILURE;
        }

        $query = AiTokenUsageEvent::query()->orderBy('id');
        if (! $this->option('force')) {
            $query->whereNull('cost_usd');
        }

        $updated = 0;
        $skipped = 0;

        $query->chunkById(200, function ($events) use (&$updated, &$skipped) {
            foreach ($events as $event) {
                $model = (string) ($event->model ?: config('openai.model', ''));
                $cost = AiTokenPricing::calculateCostUsd($model, [
                    'prompt_tokens' => (int) $event->prompt_tokens,
                    'completion_tokens' => (int) $event->completion_tokens,
                    'cached_tokens' => (int) $event->cached_tokens,
                    'reasoning_tokens' => (int) $event->reasoning_tokens,
                ]);

                if ($cost === null) {
                    $skipped++;
                    continue;
                }

                $event->update(['cost_usd' => $cost['cost_usd']]);
                $updated++;
            }
        });

        $this->info("Updated {$updated} event(s), skipped {$skipped}.");

        return self::SUCCESS;
    }
}
