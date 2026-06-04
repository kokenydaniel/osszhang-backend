<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HouseholdFileStorage
{
    public static function store(string $scope, string $directory, UploadedFile $file): array
    {
        $disk = StorageDisk::default();
        $extension = $file->getClientOriginalExtension();
        $storedName = $extension !== '' ? \Illuminate\Support\Str::uuid()->toString().'.'.$extension : \Illuminate\Support\Str::uuid()->toString();
        $path = trim($directory, '/').'/'.$storedName;
        $plaintext = file_get_contents($file->getRealPath());
        abort_unless(is_string($plaintext), 500, 'A fájl mentése nem sikerült.');
        $payload = HouseholdFileCipher::encrypt($scope, $plaintext);
        $written = Storage::disk($disk)->put($path, $payload);
        abort_unless($written !== false && $written !== '', 500, 'A fájl mentése nem sikerült.');

        return [
            'disk' => $disk,
            'path' => is_string($written) ? $written : $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ];
    }

    public static function storeRaw(string $scope, string $directory, string $storedName, string $contents, string $mime): array
    {
        $disk = StorageDisk::default();
        $path = trim($directory, '/').'/'.$storedName;
        $payload = HouseholdFileCipher::encrypt($scope, $contents);
        $written = Storage::disk($disk)->put($path, $payload);
        abort_unless($written !== false && $written !== '', 500, 'A fájl mentése nem sikerült.');

        return [
            'disk' => $disk,
            'path' => is_string($written) ? $written : $path,
            'mime' => $mime,
            'size_bytes' => strlen($contents),
        ];
    }

    public static function readDecrypted(string $scope, string $diskName, string $path): string
    {
        abort_unless(StorageLocator::exists($diskName, $path), 404);
        $raw = StorageLocator::forPath($diskName, $path)->get($path);

        return HouseholdFileCipher::decrypt($scope, $raw);
    }

    public static function downloadResponse(
        string $scope,
        string $diskName,
        string $path,
        string $downloadName,
        ?string $mime,
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($scope, $diskName, $path): void {
                echo self::readDecrypted($scope, $diskName, $path);
            },
            $downloadName,
            ['Content-Type' => $mime ?? 'application/octet-stream'],
        );
    }

    public static function delete(string $diskName, string $path): void
    {
        Storage::disk($diskName)->delete($path);
    }
}
