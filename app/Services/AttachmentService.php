<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Household;
use App\Models\InsurancePolicy;
use App\Models\RentalProperty;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Support\StorageDisk;
use App\Support\StorageLocator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function __construct(
        private readonly TransactionSensitiveData $transactionSensitive,
    ) {}
    public function store(
        Household $household,
        User $user,
        Model $attachable,
        UploadedFile $file,
    ): array {
        $this->assertAttachableOwnedByHousehold($household, $attachable);

        $disk = StorageDisk::default();
        $dir = 'attachments/'.$household->id.'/'.Str::snake(class_basename($attachable));
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $storedName, $disk);
        abort_unless(is_string($path) && $path !== '', 500, 'A fájl mentése nem sikerült.');

        $attachment = Attachment::create([
            'household_id' => $household->id,
            'uploaded_by' => $user->id,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return $this->format($attachment);
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(Model $attachable): array
    {
        return Attachment::query()
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Attachment $a) => $this->format($a))
            ->all();
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    public function downloadResponse(Attachment $attachment): BinaryFileResponse|StreamedResponse
    {
        abort_unless(StorageLocator::exists($attachment->disk, $attachment->path), 404);
        $disk = StorageLocator::forPath($attachment->disk, $attachment->path);

        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime ?? 'application/octet-stream',
        ]);
    }

    public function resolveInsurancePolicy(Household $household, int $policyId): InsurancePolicy
    {
        return InsurancePolicy::query()
            ->where('household_id', $household->id)
            ->whereKey($policyId)
            ->firstOrFail();
    }

    public function resolveRentalProperty(Household $household, int $propertyId): RentalProperty
    {
        return RentalProperty::query()
            ->where('household_id', $household->id)
            ->whereKey($propertyId)
            ->firstOrFail();
    }

    public function resolveLedgerEntry(Household $household, int $ledgerEntryId): LedgerEntry
    {
        $entry = LedgerEntry::query()->whereKey($ledgerEntryId)->first();

        if ($entry) {
            if ($entry->transaction_id) {
                $transaction = Transaction::query()->whereKey($entry->transaction_id)->firstOrFail();
                abort_if($transaction->household_id !== $household->id, 404);
            }

            return $entry;
        }

        abort(404);
    }

    public function resolveBudgetLedgerItem(Household $household, int $transactionId, int $itemId): LedgerEntry
    {
        $transaction = Transaction::query()
            ->where('household_id', $household->id)
            ->whereKey($transactionId)
            ->firstOrFail();

        $existing = LedgerEntry::query()
            ->where('transaction_id', $transaction->id)
            ->whereKey($itemId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $sensitive = $this->transactionSensitive->resolve($transaction, $household);
        $subItems = $sensitive['subItems'] ?? [];
        $item = collect($subItems)->first(fn (array $i) => (int) ($i['id'] ?? 0) === $itemId);
        abort_unless($item, 404);

        $matched = LedgerEntry::query()
            ->where('transaction_id', $transaction->id)
            ->where('date', $item['date'])
            ->where('amount', (float) ($item['amount'] ?? 0))
            ->where('reason', (string) ($item['reason'] ?? ''))
            ->first();

        $entry = $matched ?? LedgerEntry::create([
            'transaction_id' => $transaction->id,
            'date' => $item['date'],
            'amount' => (float) ($item['amount'] ?? 0),
            'reason' => (string) ($item['reason'] ?? ''),
        ]);

        if ($itemId !== (int) $entry->id) {
            $sensitive['subItems'] = collect($subItems)
                ->map(function (array $i) use ($itemId, $entry) {
                    if ((int) ($i['id'] ?? 0) !== $itemId) {
                        return $i;
                    }

                    return [...$i, 'id' => (int) $entry->id];
                })
                ->values()
                ->all();

            $this->transactionSensitive->persistSensitive($transaction, $household, $sensitive);
            $transaction->save();
        }

        return $entry;
    }

    public function assertHouseholdOwnsAttachment(Household $household, Attachment $attachment): void
    {
        abort_if($attachment->household_id !== $household->id, 404);
    }

    private function assertAttachableOwnedByHousehold(Household $household, Model $attachable): void
    {
        if ($attachable instanceof Transaction) {
            abort_if($attachable->household_id !== $household->id, 404);

            return;
        }

        if ($attachable instanceof LedgerEntry) {
            $this->resolveLedgerEntry($household, (int) $attachable->getKey());

            return;
        }

        if ($attachable instanceof InsurancePolicy) {
            abort_if($attachable->household_id !== $household->id, 404);
            abort_unless($household->insurance_enabled, 403, 'A biztosítások modul nincs bekapcsolva.');

            return;
        }

        if ($attachable instanceof RentalProperty) {
            abort_if($attachable->household_id !== $household->id, 404);
            abort_unless($household->rental_enabled, 403, 'A bérbeadás modul nincs bekapcsolva.');

            return;
        }

        abort(422, 'Nem támogatott csatolás.');
    }

    /** @return array<string, mixed> */
    private function format(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'originalName' => $attachment->original_name,
            'mime' => $attachment->mime,
            'sizeBytes' => $attachment->size_bytes,
            'createdAt' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
