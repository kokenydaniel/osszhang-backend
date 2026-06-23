<?php

namespace App\Services;

use App\Models\Household;
use App\Models\ReceivableContact;
use App\Models\ReceivableEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ReceivableService
{
    private const ENTRY_TYPES = ['lent', 'repaid'];

    private const SOURCES = ['savings', 'transfer', 'cash'];

    public function index(User $user): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        $contacts = ReceivableContact::query()
            ->where('household_id', $household->id)
            ->with(['entries'])
            ->orderBy('name')
            ->get()
            ->map(fn (ReceivableContact $c) => $this->formatContact($c))
            ->all();

        return [
            'contacts' => $contacts,
            'summary' => $this->buildSummary($contacts),
        ];
    }

    public function createContact(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        $contact = ReceivableContact::create([
            'household_id' => $household->id,
            'name' => trim($validated['name']),
            'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
        ]);

        return $this->formatContact($contact->fresh()->load('entries'));
    }

    public function updateContact(User $user, int $id, array $validated): array
    {
        $contact = $this->findContactForUser($user, $id);

        $payload = [];
        if (array_key_exists('name', $validated)) {
            $payload['name'] = trim((string) $validated['name']);
        }
        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'] !== null ? trim((string) $validated['note']) : null;
        }
        $contact->update($payload);

        return $this->formatContact($contact->fresh()->load('entries'));
    }

    public function deleteContact(User $user, int $id): array
    {
        $contact = $this->findContactForUser($user, $id);
        $formatted = $this->formatContact($contact->load('entries'));
        $contact->delete();

        return $formatted;
    }

    public function createEntry(User $user, int $contactId, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $contact = $this->findContactForUser($user, $contactId);

        ReceivableEntry::create([
            'household_id' => $household->id,
            'receivable_contact_id' => $contact->id,
            'entry_type' => $this->normalizeEntryType($validated['entryType'] ?? $validated['entry_type'] ?? ''),
            'amount' => (float) ($validated['amount'] ?? 0),
            'currency' => strtoupper((string) ($validated['currency'] ?? config('receivables.default_currency', 'HUF'))),
            'source' => $this->normalizeSource($validated['source'] ?? ''),
            'entry_date' => $validated['entryDate'] ?? $validated['entry_date'],
            'note' => isset($validated['note']) ? trim((string) $validated['note']) : null,
        ]);

        return $this->formatContact($contact->fresh()->load('entries'));
    }

    public function updateEntry(User $user, int $entryId, array $validated): array
    {
        $entry = $this->findEntryForUser($user, $entryId);
        $contact = $entry->contact;

        $payload = [];
        if (isset($validated['entryType']) || isset($validated['entry_type'])) {
            $payload['entry_type'] = $this->normalizeEntryType($validated['entryType'] ?? $validated['entry_type'] ?? $entry->entry_type);
        }
        if (isset($validated['amount'])) {
            $payload['amount'] = (float) $validated['amount'];
        }
        if (isset($validated['currency'])) {
            $payload['currency'] = strtoupper((string) $validated['currency']);
        }
        if (isset($validated['source'])) {
            $payload['source'] = $this->normalizeSource($validated['source']);
        }
        if (isset($validated['entryDate']) || isset($validated['entry_date'])) {
            $payload['entry_date'] = $validated['entryDate'] ?? $validated['entry_date'];
        }
        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'] !== null ? trim((string) $validated['note']) : null;
        }

        $entry->update($payload);

        return $this->formatContact($contact->fresh()->load('entries'));
    }

    public function deleteEntry(User $user, int $entryId): array
    {
        $entry = $this->findEntryForUser($user, $entryId);
        $contact = $entry->contact;
        $entry->delete();

        return $this->formatContact($contact->fresh()->load('entries'));
    }

    private function requireHousehold(User $user): Household
    {
        $household = $user->household;
        if (! $household) {
            throw new AuthorizationException('Nincs háztartás.');
        }

        return $household;
    }

    private function assertModuleEnabled(Household $household): void
    {
        abort_unless($household->receivables_enabled, 403, 'A kintlévőség modul nincs bekapcsolva.');
    }

    private function findContactForUser(User $user, int $id): ReceivableContact
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return ReceivableContact::query()
            ->where('household_id', $household->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findEntryForUser(User $user, int $id): ReceivableEntry
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return ReceivableEntry::query()
            ->where('household_id', $household->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function normalizeEntryType(string $type): string
    {
        $type = strtolower(trim($type));
        abort_unless(in_array($type, self::ENTRY_TYPES, true), 422, 'Érvénytelen tétel típus.');

        return $type;
    }

    private function normalizeSource(string $source): string
    {
        $source = strtolower(trim($source));
        abort_unless(in_array($source, self::SOURCES, true), 422, 'Érvénytelen forrás.');

        return $source;
    }

    private function formatContact(ReceivableContact $contact): array
    {
        $entries = $contact->relationLoaded('entries')
            ? $contact->entries->map(fn (ReceivableEntry $e) => $this->formatEntry($e))->all()
            : [];

        $totals = $this->totalsFromEntries($entries);

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'note' => $contact->note,
            'totalLent' => $totals['totalLent'],
            'totalRepaid' => $totals['totalRepaid'],
            'outstanding' => $totals['outstanding'],
            'isSettled' => $totals['isSettled'],
            'entries' => $entries,
            'createdAt' => $contact->created_at?->toIso8601String(),
            'updatedAt' => $contact->updated_at?->toIso8601String(),
        ];
    }

    private function formatEntry(ReceivableEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'receivableContactId' => $entry->receivable_contact_id,
            'entryType' => $entry->entry_type,
            'amount' => (float) $entry->amount,
            'currency' => $entry->currency,
            'source' => $entry->source,
            'entryDate' => $entry->entry_date?->format('Y-m-d'),
            'note' => $entry->note,
            'createdAt' => $entry->created_at?->toIso8601String(),
        ];
    }

    private function totalsFromEntries(array $entries): array
    {
        $totalLent = 0.0;
        $totalRepaid = 0.0;

        foreach ($entries as $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            if (($entry['entryType'] ?? '') === 'repaid') {
                $totalRepaid += $amount;
            } else {
                $totalLent += $amount;
            }
        }

        $outstanding = round($totalLent - $totalRepaid, 2);

        return [
            'totalLent' => round($totalLent, 2),
            'totalRepaid' => round($totalRepaid, 2),
            'outstanding' => $outstanding,
            'isSettled' => $outstanding <= 0.005 && ($totalLent > 0 || $totalRepaid > 0),
        ];
    }

    private function buildSummary(array $contacts): array
    {
        $totalLent = 0.0;
        $totalRepaid = 0.0;
        $openContactCount = 0;

        foreach ($contacts as $contact) {
            $totalLent += (float) ($contact['totalLent'] ?? 0);
            $totalRepaid += (float) ($contact['totalRepaid'] ?? 0);
            if (! ($contact['isSettled'] ?? true) && ((float) ($contact['outstanding'] ?? 0)) > 0.005) {
                $openContactCount++;
            }
        }

        $totalOutstanding = round($totalLent - $totalRepaid, 2);

        return [
            'totalLent' => round($totalLent, 2),
            'totalRepaid' => round($totalRepaid, 2),
            'totalOutstanding' => max(0, $totalOutstanding),
            'openContactCount' => $openContactCount,
            'contactCount' => count($contacts),
        ];
    }
}
