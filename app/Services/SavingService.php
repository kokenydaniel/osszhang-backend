<?php

namespace App\Services;

use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Saving;

class SavingService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function listForHousehold(Household $household): array
    {
        return Saving::where('household_id', $household->id)
            ->with('ledger')
            ->get()
            ->map(fn ($s) => $this->crypto->formatSaving($s, $household))
            ->all();
    }

    public function create(Household $household, array $validated): array
    {
        $saving = new Saving([
            'household_id' => $household->id,
            'count_in_savings' => $validated['count_in_savings'] ?? true,
        ]);
        $this->crypto->persistSaving($saving, $household, [
            'institution' => $validated['institution'],
            'currency' => $validated['currency'],
            'owner' => $validated['owner'] ?? 'Közös',
        ]);
        $saving->save();

        return $this->crypto->formatSaving($saving->load('ledger'), $household);
    }

    public function addEntry(Household $household, int|string $id, array $validated): array
    {
        $saving = Saving::where('household_id', $household->id)->findOrFail($id);

        $entry = new LedgerEntry([
            'saving_id' => $saving->id,
            'date' => $validated['date'],
        ]);
        $this->crypto->persistLedger($entry, $household, [
            'amount' => (float) $validated['amount'],
            'reason' => $validated['reason'],
        ]);
        $entry->save();

        return $this->crypto->formatSaving($saving->load('ledger'), $household);
    }

    public function updateEntry(Household $household, int|string $savingId, int|string $entryId, array $validated): array
    {
        $saving = Saving::where('household_id', $household->id)->findOrFail($savingId);
        $entry = LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId);

        $sensitive = $this->crypto->ledgerResolved($entry, $household);
        if (array_key_exists('amount', $validated)) {
            $sensitive['amount'] = (float) $validated['amount'];
        }
        if (array_key_exists('reason', $validated)) {
            $sensitive['reason'] = $validated['reason'];
        }
        if (array_key_exists('date', $validated)) {
            $entry->date = $validated['date'];
        }

        $this->crypto->persistLedger($entry, $household, $sensitive);
        $entry->save();

        return $this->crypto->formatSaving($saving->load('ledger'), $household);
    }

    public function deleteEntry(Household $household, int|string $savingId, int|string $entryId): array
    {
        $saving = Saving::where('household_id', $household->id)->findOrFail($savingId);
        LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId)->delete();

        return $this->crypto->formatSaving($saving->load('ledger'), $household);
    }

    public function update(Household $household, int|string $id, array $validated): array
    {
        $saving = Saving::where('household_id', $household->id)->findOrFail($id);

        $sensitive = $this->crypto->savingResolved($saving, $household);
        if (array_key_exists('institution', $validated)) {
            $sensitive['institution'] = $validated['institution'];
        }
        if (array_key_exists('currency', $validated)) {
            $sensitive['currency'] = $validated['currency'];
        }
        if (array_key_exists('owner', $validated)) {
            $sensitive['owner'] = $validated['owner'];
        }
        if (array_key_exists('count_in_savings', $validated)) {
            $saving->count_in_savings = $validated['count_in_savings'];
        }

        $this->crypto->persistSaving($saving, $household, $sensitive);
        $saving->save();

        return $this->crypto->formatSaving($saving->load('ledger'), $household);
    }

    public function delete(int $householdId, int|string $id): void
    {
        Saving::where('household_id', $householdId)->findOrFail($id)->delete();
    }
}
