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

        return [
            'id' => $saving->id,
            'institution' => (string) ($s['institution'] ?? ''),
            'currency' => (string) ($s['currency'] ?? ''),
            'owner' => (string) ($s['owner'] ?? ''),
            'count_in_savings' => (bool) $saving->count_in_savings,
            'ledger' => $ledger,
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
