<?php

namespace App\Services;

use App\Models\BusinessDocument;
use App\Models\BusinessOrder;
use App\Models\Household;
use App\Models\User;
use App\Support\BusinessDocumentTypes;
use App\Support\StorageDisk;
use App\Support\StorageLocator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BusinessDocumentService
{
    public function listForMonth(Household $household, int $year, int $month): array
    {
        return BusinessDocument::query()
            ->where('household_id', $household->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('document_type')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BusinessDocument $doc) => $this->format($doc))
            ->all();
    }

    public function store(
        Household $household,
        User $user,
        int $year,
        int $month,
        string $documentType,
        UploadedFile $file,
        ?int $businessOrderId = null,
        ?string $label = null,
    ): array {
        abort_unless(BusinessDocumentTypes::isValid($documentType), 422, 'Érvénytelen dokumentum típus.');

        if ($businessOrderId !== null) {
            $order = BusinessOrder::query()
                ->where('household_id', $household->id)
                ->whereKey($businessOrderId)
                ->firstOrFail();
            abort_unless(
                (int) substr((string) $order->date, 0, 4) === $year
                && (int) substr((string) $order->date, 5, 2) === $month,
                422,
                'A rendelés nem esik ebbe a hónapba.',
            );
        }

        $disk = StorageDisk::default();
        $dir = 'business-documents/'.$household->id.'/'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $storedName = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $storedName, $disk);
        abort_unless(is_string($path) && $path !== '', 500, 'A fájl mentése nem sikerült.');

        $document = BusinessDocument::create([
            'household_id' => $household->id,
            'uploaded_by' => $user->id,
            'year' => $year,
            'month' => $month,
            'document_type' => $documentType,
            'business_order_id' => $businessOrderId,
            'label' => $label ? trim($label) : null,
            'source' => 'manual',
            'import_key' => null,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return $this->format($document);
    }

    public function storeFromContents(
        Household $household,
        User $user,
        int $year,
        int $month,
        string $documentType,
        string $originalName,
        string $contents,
        string $mime,
        string $source = 'manual',
        ?string $importKey = null,
        ?string $label = null,
    ): array {
        abort_unless(BusinessDocumentTypes::isValid($documentType), 422, 'Érvénytelen dokumentum típus.');

        $disk = StorageDisk::default();
        $dir = 'business-documents/'.$household->id.'/'.$year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $dir.'/'.$storedName;

        Storage::disk($disk)->put($path, $contents);

        $document = BusinessDocument::create([
            'household_id' => $household->id,
            'uploaded_by' => $user->id,
            'year' => $year,
            'month' => $month,
            'document_type' => $documentType,
            'business_order_id' => null,
            'label' => $label,
            'source' => $source,
            'import_key' => $importKey,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $originalName,
            'mime' => $mime,
            'size_bytes' => strlen($contents),
        ]);

        return $this->format($document);
    }

    public function clearImportedSourceForMonth(Household $household, int $year, int $month, string $source): void
    {
        $docs = BusinessDocument::query()
            ->where('household_id', $household->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('source', $source)
            ->get();

        foreach ($docs as $doc) {
            $this->delete($doc);
        }
    }

    public function delete(BusinessDocument $document): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();
    }

    public function downloadResponse(BusinessDocument $document): BinaryFileResponse|StreamedResponse
    {
        abort_unless(StorageLocator::exists($document->disk, $document->path), 404);
        $disk = StorageLocator::forPath($document->disk, $document->path);

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?? 'application/octet-stream',
        ]);
    }

    public function bundleZipResponse(Household $household, int $year, int $month): BinaryFileResponse
    {
        /** @var Collection<int, BusinessDocument> $documents */
        $documents = BusinessDocument::query()
            ->where('household_id', $household->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('document_type')
            ->orderBy('original_name')
            ->get();

        abort_if($documents->isEmpty(), 404, 'Nincs feltöltött dokumentum ebben a hónapban.');

        $monthLabel = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $zipName = 'konyvelesi-anyag-'.$year.'-'.$monthLabel.'.zip';
        $tempPath = storage_path('app/temp/'.Str::uuid()->toString().'.zip');

        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $zip = new ZipArchive;
        abort_unless($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        foreach ($documents as $index => $doc) {
            if (! StorageLocator::exists($doc->disk, $doc->path)) {
                continue;
            }
            $folder = $this->zipFolderForType($doc->document_type);
            $entryName = $this->uniqueZipEntryName($folder, $doc->original_name, $index);
            $zip->addFromString($entryName, StorageLocator::forPath($doc->disk, $doc->path)->get($doc->path));
        }

        $zip->close();

        return response()->download($tempPath, $zipName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /** @return array<string, mixed> */
    private function format(BusinessDocument $document): array
    {
        return [
            'id' => $document->id,
            'year' => $document->year,
            'month' => $document->month,
            'documentType' => $document->document_type,
            'businessOrderId' => $document->business_order_id,
            'label' => $document->label,
            'source' => $document->source,
            'importKey' => $document->import_key,
            'originalName' => $document->original_name,
            'mime' => $document->mime,
            'sizeBytes' => $document->size_bytes,
            'createdAt' => $document->created_at?->toIso8601String(),
        ];
    }

    private function zipFolderForType(string $type): string
    {
        return match ($type) {
            BusinessDocumentTypes::BANK_STATEMENT => '01-bank',
            BusinessDocumentTypes::SUMUP_REPORT => '02-sumup',
            BusinessDocumentTypes::BARION_REPORT => '03-barion',
            BusinessDocumentTypes::MARKET_RECEIPT => '04-piaci-nyugtak',
            default => '99-egyeb',
        };
    }

    private function uniqueZipEntryName(string $folder, string $originalName, int $index): string
    {
        $base = preg_replace('/[^\p{L}\p{N}\.\-\_\s]/u', '_', $originalName) ?: 'fajl';
        $suffix = $index > 0 ? '-'.$index : '';

        return $folder.'/'.$base.$suffix;
    }
}
