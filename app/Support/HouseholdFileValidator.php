<?php

namespace App\Support;

final class HouseholdFileValidator
{
    public static function isValidPayload(string $bytes, ?string $mime, ?string $originalName): bool
    {
        if ($bytes === '' || HouseholdFileCipher::looksLikeEncryptedPayload($bytes)) {
            return false;
        }

        $name = strtolower(trim((string) $originalName));
        $type = strtolower(trim((string) $mime));

        if (self::isPdf($name, $type)) {
            return self::isValidPdf($bytes);
        }

        if (self::isPng($name, $type)) {
            return str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
                || str_starts_with($bytes, "\x89PNG");
        }

        if (self::isJpeg($name, $type)) {
            return str_starts_with($bytes, "\xFF\xD8\xFF");
        }

        if (self::isWebp($name, $type)) {
            return str_starts_with($bytes, 'RIFF') && str_contains(substr($bytes, 0, 16), 'WEBP');
        }

        if (self::isSpreadsheet($name, $type)) {
            return self::isValidSpreadsheet($bytes, $name);
        }

        return strlen($bytes) >= 4;
    }

    public static function isValidPdf(string $bytes): bool
    {
        if (! str_starts_with($bytes, '%PDF') || strlen($bytes) < 128) {
            return false;
        }

        $tail = substr($bytes, -65536);

        return str_contains($tail, '%%EOF');
    }

    private static function isPdf(string $name, string $type): bool
    {
        return str_ends_with($name, '.pdf') || str_contains($type, 'pdf');
    }

    private static function isPng(string $name, string $type): bool
    {
        return str_ends_with($name, '.png') || str_contains($type, 'png');
    }

    private static function isJpeg(string $name, string $type): bool
    {
        return str_ends_with($name, '.jpg')
            || str_ends_with($name, '.jpeg')
            || str_contains($type, 'jpeg')
            || str_contains($type, 'jpg');
    }

    private static function isWebp(string $name, string $type): bool
    {
        return str_ends_with($name, '.webp') || str_contains($type, 'webp');
    }

    private static function isSpreadsheet(string $name, string $type): bool
    {
        return str_ends_with($name, '.xls')
            || str_ends_with($name, '.xlsx')
            || str_ends_with($name, '.csv')
            || str_contains($type, 'spreadsheet')
            || str_contains($type, 'excel')
            || str_contains($type, 'csv');
    }

    private static function isValidSpreadsheet(string $bytes, string $name): bool
    {
        if (str_ends_with($name, '.csv')) {
            return strlen($bytes) >= 2;
        }

        if (str_ends_with($name, '.xlsx')) {
            return str_starts_with($bytes, "PK\x03\x04");
        }

        if (str_ends_with($name, '.xls')) {
            return str_starts_with($bytes, "\xD0\xCF\x11\xE0")
                || str_starts_with($bytes, "PK\x03\x04");
        }

        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "\xD0\xCF\x11\xE0");
    }
}
