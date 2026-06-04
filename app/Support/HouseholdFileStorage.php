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
            'size_bytes' => strlen($plaintext),
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

    public static function readDecrypted(
        string $scope,
        string $diskName,
        string $path,
        ?int $expectedPlainBytes = null,
    ): string {
        $plaintext = self::tryReadDecrypted($scope, $diskName, $path, $expectedPlainBytes);
        abort_if($plaintext === null, 404, 'A fájl nem olvasható — töltsd fel újra.');

        return $plaintext;
    }

    public static function tryReadDecrypted(
        string $scope,
        string $diskName,
        string $path,
        ?int $expectedPlainBytes = null,
    ): ?string {
        $raw = StorageLocator::read($diskName, $path);
        if ($raw === null) {
            return null;
        }

        try {
            $plaintext = HouseholdFileCipher::decrypt($scope, $raw);
        } catch (\Throwable) {
            return null;
        }

        if (! self::plaintextIntegrityOk($plaintext, $expectedPlainBytes)) {
            return null;
        }

        return $plaintext;
    }

    public static function downloadResponse(
        string $scope,
        string $diskName,
        string $path,
        string $downloadName,
        ?string $mime,
        ?int $expectedPlainBytes = null,
    ): Response {
        $bytes = self::readDecrypted($scope, $diskName, $path, $expectedPlainBytes);
        $contentType = self::binaryContentType($mime);

        return new Response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => self::contentDisposition($downloadName),
            'Content-Length' => (string) strlen($bytes),
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function binaryContentType(?string $mime): string
    {
        $type = trim((string) $mime);

        if ($type === '' || str_contains(strtolower($type), 'charset')) {
            return 'application/octet-stream';
        }

        return $type;
    }

    private static function plaintextIntegrityOk(string $plaintext, ?int $expectedPlainBytes): bool
    {
        if ($plaintext === '') {
            return false;
        }

        if (HouseholdFileCipher::looksLikeEncryptedPayload($plaintext)) {
            return false;
        }

        if ($expectedPlainBytes !== null && $expectedPlainBytes > 0) {
            $actual = strlen($plaintext);
            if ($actual === $expectedPlainBytes) {
                return true;
            }

            return HouseholdFileCipher::hasBinaryDocumentMagic($plaintext);
        }

        return true;
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
