<?php

namespace App\Services\Formatters;

use App\Models\Household;
use App\Services\HouseholdCipherService;
use Illuminate\Support\Facades\Log;

abstract class AbstractEncryptedRecordFormatter
{
    public function __construct(
        protected readonly HouseholdCipherService $cipher,
    ) {}

    protected function ensureKey(Household $household): void
    {
        $this->cipher->ensureCipherKey($household);
    }

    protected function decrypt(Household $household, ?string $blob): ?array
    {
        if (! $blob) {
            return null;
        }

        try {
            return $this->cipher->decrypt($household, $blob);
        } catch (\Throwable $e) {
            Log::warning('household.decrypt_failed', [
                'household_id' => $household->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function resolve(Household $household, ?string $blob, array $legacy): array
    {
        return $this->decrypt($household, $blob) ?? $legacy;
    }

    protected function persist(Household $household, object $model, array $sensitive, array $masked): void
    {
        $this->ensureKey($household);
        $model->encrypted_payload = $this->cipher->encrypt($household, $sensitive);
        foreach ($masked as $key => $value) {
            $model->{$key} = $value;
        }
    }
}
