<?php

namespace App\Support;

final class AiTokenPricing
{
    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int, cached_tokens?: int, reasoning_tokens?: int}  $usage
     * @return array{cost_usd: float, rates: array{input_per_million: float, output_per_million: float, cached_per_million: float|null, model: string}}|null
     */
    public static function calculateCostUsd(string $model, array $usage): ?array
    {
        $rates = self::ratesForModel($model);
        if ($rates === null) {
            return null;
        }

        $promptTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completionTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $cachedTokens = max(0, (int) ($usage['cached_tokens'] ?? 0));
        $reasoningTokens = max(0, (int) ($usage['reasoning_tokens'] ?? 0));

        if ($cachedTokens > 0 && $rates['cached_per_million'] !== null) {
            $uncachedPromptTokens = max(0, $promptTokens - $cachedTokens);
            $cachedCost = ($cachedTokens / 1_000_000) * $rates['cached_per_million'];
        } else {
            $uncachedPromptTokens = $promptTokens;
            $cachedCost = 0.0;
        }

        $outputTokens = $completionTokens + $reasoningTokens;

        $inputCost = ($uncachedPromptTokens / 1_000_000) * $rates['input_per_million'];
        $outputCost = ($outputTokens / 1_000_000) * $rates['output_per_million'];
        $costUsd = round($inputCost + $cachedCost + $outputCost, 8);

        return [
            'cost_usd' => $costUsd,
            'rates' => $rates,
        ];
    }

    /** @return array{input_per_million: float, output_per_million: float, cached_per_million: float|null, model: string}|null */
    public static function ratesForModel(string $model): ?array
    {
        $normalized = self::normalizeModel($model);
        $configured = self::configuredModels();

        if ($normalized !== '' && isset($configured[$normalized])) {
            return $configured[$normalized];
        }

        foreach (self::sortedModelKeys($configured) as $key) {
            if ($normalized !== '' && str_starts_with($normalized, $key)) {
                return $configured[$key];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $configured
     * @return list<string>
     */
    private static function sortedModelKeys(array $configured): array
    {
        $keys = array_keys($configured);
        usort($keys, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $keys;
    }

    /** @return array<string, array{input_per_million: float, output_per_million: float, cached_per_million: float|null, model: string}> */
    public static function configuredModels(): array
    {
        /** @var array<string, mixed> $official */
        $official = config('openai_model_pricing.models', []);
        /** @var array<string, mixed> $overrides */
        $overrides = config('openai.pricing.overrides', []);

        $normalized = [];

        foreach ([$official, $overrides] as $source) {
            if (! is_array($source)) {
                continue;
            }

            foreach ($source as $model => $rates) {
                if (! is_array($rates)) {
                    continue;
                }

                $key = self::normalizeModel((string) $model);
                if ($key === '') {
                    continue;
                }

                $parsed = self::normalizeRates($key, $rates);
                if ($parsed !== null) {
                    $normalized[$key] = $parsed;
                }
            }
        }

        return $normalized;
    }

    /** @return array{source_url: string, last_verified: string|null, tier: string|null, default_model: string, default_model_rates: array<string, mixed>|null} */
    public static function pricingMeta(): array
    {
        $defaultModel = (string) config('openai.model', 'gpt-4o-mini');

        return [
            'source_url' => (string) config('openai_model_pricing.source_url', 'https://platform.openai.com/docs/pricing'),
            'last_verified' => config('openai_model_pricing.last_verified'),
            'tier' => config('openai_model_pricing.tier'),
            'default_model' => $defaultModel,
            'default_model_rates' => self::ratesForModel($defaultModel),
        ];
    }

    public static function isConfigured(): bool
    {
        return self::configuredModels() !== [];
    }

    /** @param  array<string, mixed>  $rates
     * @return array{input_per_million: float, output_per_million: float, cached_per_million: float|null, model: string}|null
     */
    private static function normalizeRates(string $model, array $rates): ?array
    {
        $input = self::readRate($rates, ['input', 'input_per_million']);
        $output = self::readRate($rates, ['output', 'output_per_million']);

        if ($input === null || $output === null) {
            return null;
        }

        $cached = self::readRate($rates, ['cached', 'cached_per_million', 'cached_input', 'cached_input_per_million']);

        return [
            'input_per_million' => $input,
            'output_per_million' => $output,
            'cached_per_million' => $cached,
            'model' => $model,
        ];
    }

    /** @param  array<string, mixed>  $rates
     * @param  list<string>  $keys
     */
    private static function readRate(array $rates, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $rates)) {
                continue;
            }

            $value = (float) $rates[$key];
            if ($value >= 0) {
                return $value;
            }
        }

        return null;
    }

    private static function normalizeModel(string $model): string
    {
        return strtolower(trim($model));
    }
}
