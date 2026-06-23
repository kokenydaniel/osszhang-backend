<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\Wallet;

class WalletProvisioningService
{
    public function ensureSharedWallet(Household $household): Wallet
    {
        $existing = Wallet::query()
            ->where('household_id', $household->id)
            ->where('is_shared', true)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Wallet::create([
            'household_id' => $household->id,
            'name' => Wallet::SHARED_WALLET_NAME,
            'is_shared' => true,
            'owner_id' => null,
        ]);
    }

    public function migrateLegacyData(): array
    {
        $stats = [
            'households' => 0,
            'wallets_created' => 0,
            'transactions_linked' => 0,
        ];

        Household::query()->orderBy('id')->chunkById(100, function ($households) use (&$stats) {
            foreach ($households as $household) {
                $stats['households']++;

                $hadSharedWallet = Wallet::query()
                    ->where('household_id', $household->id)
                    ->where('is_shared', true)
                    ->exists();

                $wallet = $this->ensureSharedWallet($household);

                if (! $hadSharedWallet) {
                    $stats['wallets_created']++;
                }

                $linked = Transaction::query()
                    ->where('household_id', $household->id)
                    ->whereNull('wallet_id')
                    ->update(['wallet_id' => $wallet->id]);

                $stats['transactions_linked'] += $linked;
            }
        });

        return $stats;
    }

    public function assertAllTransactionsLinked(): void
    {
        $orphans = Transaction::query()->whereNull('wallet_id')->count();

        if ($orphans > 0) {
            throw new \RuntimeException(
                "{$orphans} tranzakció maradt wallet nélkül. Futtasd: php artisan wallets:migrate-legacy"
            );
        }
    }

    public function sharedWalletForHousehold(Household $household): Wallet
    {
        return $this->ensureSharedWallet($household);
    }

    public function defaultWalletIdForHousehold(Household $household): int
    {
        return $this->ensureSharedWallet($household)->id;
    }
}
