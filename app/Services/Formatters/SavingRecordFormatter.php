<?php

namespace App\Services\Formatters;

use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Saving;

class SavingRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function savingLegacy(Saving $saving): array
    {
        return [
            'institution' => (string) $saving->institution,
            'currency' => (string) $saving->currency,
            'owner' => (string) $saving->owner,
        ];
    }

    public function savingResolved(Saving $saving, Household $household): array
    {
        return $this->resolve($household, $saving->encrypted_payload, $this->savingLegacy($saving));
    }

    public function persistSaving(Saving $saving, Household $household, array $sensitive): void
    {
        $this->persist($household, $saving, $sensitive, [
            'institution' => '—',
            'currency' => '—',
            'owner' => '—',
        ]);
    }

    public function ledgerLegacy(LedgerEntry $entry): array
    {
        return [
            'amount' => (float) $entry->amount,
            'reason' => (string) $entry->reason,
        ];
    }

    public function ledgerResolved(LedgerEntry $entry, Household $household): array
    {
        return $this->resolve($household, $entry->encrypted_payload, $this->ledgerLegacy($entry));
    }

    public function persistLedger(LedgerEntry $entry, Household $household, array $sensitive): void
    {
        $this->persist($household, $entry, $sensitive, [
            'amount' => 0,
            'reason' => '—',
        ]);
    }

    public function formatSaving(Saving $saving, Household $household): array
    {
        $s = $this->savingResolved($saving, $household);
        $ledger = $saving->relationLoaded('ledger')
            ? $saving->ledger->map(fn (LedgerEntry $e) => $this->formatLedgerEntry($e, $household))->values()->all()
            : [];

        $currentAmount = ($saving->type ?? Saving::TYPE_ACCOUNT) === Saving::TYPE_GOAL
            ? $this->goalLedgerTotal($saving, $household)
            : (float) $saving->current_amount;

        return [
            'id' => $saving->id,
            'type' => $saving->type ?? Saving::TYPE_ACCOUNT,
            'walletId' => $saving->wallet_id,
            'wallet_id' => $saving->wallet_id,
            'institution' => (string) ($s['institution'] ?? ''),
            'currency' => (string) ($s['currency'] ?? ''),
            'owner' => (string) ($s['owner'] ?? ''),
            'count_in_savings' => (bool) $saving->count_in_savings,
            'goalAmount' => (float) $saving->goal_amount,
            'goal_amount' => (float) $saving->goal_amount,
            'currentAmount' => $currentAmount,
            'current_amount' => $currentAmount,
            'targetDate' => $saving->target_date?->toDateString(),
            'target_date' => $saving->target_date?->toDateString(),
            'wallet' => $this->formatWallet($saving),
            'ledger' => $ledger,
        ];
    }

    private function goalLedgerTotal(Saving $saving, Household $household): float
    {
        if (! $saving->relationLoaded('ledger')) {
            return (float) $saving->current_amount;
        }

        if ($saving->ledger->isEmpty()) {
            return 0.0;
        }

        return (float) $saving->ledger->sum(
            fn (LedgerEntry $entry) => abs((float) ($this->ledgerResolved($entry, $household)['amount'] ?? 0)),
        );
    }

    /** @return array<string, mixed>|null */
    private function formatWallet(Saving $saving): ?array
    {
        if (! $saving->relationLoaded('wallet') || $saving->wallet === null) {
            return null;
        }

        return [
            'id' => $saving->wallet->id,
            'name' => $saving->wallet->name,
            'isShared' => (bool) $saving->wallet->is_shared,
            'is_shared' => (bool) $saving->wallet->is_shared,
        ];
    }

    public function formatLedgerEntry(LedgerEntry $entry, Household $household): array
    {
        $s = $this->ledgerResolved($entry, $household);

        return [
            'id' => $entry->id,
            'date' => $entry->date,
            'amount' => (float) ($s['amount'] ?? 0),
            'reason' => (string) ($s['reason'] ?? ''),
            'saving_id' => $entry->saving_id,
            'transaction_id' => $entry->transaction_id,
        ];
    }
}
