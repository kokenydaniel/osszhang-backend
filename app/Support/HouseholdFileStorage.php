<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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
        $plaintext = self::tryReadDecrypted($scope, $diskName, $path);
        abort_if($plaintext === null, 404);

        return $plaintext;
    }

    public static function tryReadDecrypted(string $scope, string $diskName, string $path): ?string
    {
        $raw = StorageLocator::read($diskName, $path);
        if ($raw === null) {
            return null;
        }

        try {
            return HouseholdFileCipher::decrypt($scope, $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function downloadResponse(
        string $scope,
        string $diskName,
        string $path,
        string $downloadName,
        ?string $mime,
    ): Response {
        $bytes = self::readDecrypted($scope, $diskName, $path);
        $contentType = $mime ?? 'application/octet-stream';

        return new Response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => self::contentDisposition($downloadName),
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public static function delete(string $diskName, string $path): void
    {
        Storage::disk($diskName)->delete($path);
    }

    private static function contentDisposition(string $filename): string
    {
        $safe = trim($filename) !== '' ? trim($filename) : 'letoltes';
        $ascii = preg_replace('/[^\w.\- ]+/u', '_', $safe) ?: 'letoltes';

        return 'attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($safe);
    }
}
