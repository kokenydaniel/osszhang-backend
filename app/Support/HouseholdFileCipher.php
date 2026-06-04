<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

final class HouseholdFileCipher
{
    public static function encrypt(string $scope, string $plaintext): string
    {
        return self::encrypter($scope)->encrypt($plaintext);
    }

    public static function decrypt(string $scope, string $payload): string
    {
        try {
            return self::encrypter($scope)->decrypt($payload);
        } catch (DecryptException $exception) {
            if (self::looksLikeEncryptedPayload($payload)) {
                throw $exception;
            }

            return $payload;
        }
    }

    public static function looksLikeEncryptedPayload(string $payload): bool
    {
        if ($payload === '' || self::hasBinaryDocumentMagic($payload)) {
            return false;
        }

        $trim = ltrim($payload);
        if (str_starts_with($trim, 'eyJ')) {
            return true;
        }

        $decoded = base64_decode($trim, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value']);
    }

    public static function hasBinaryDocumentMagic(string $bytes): bool
    {
        if ($bytes === '') {
            return false;
        }

        return str_starts_with($bytes, '%PDF')
            || str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "\x89PNG")
            || str_starts_with($bytes, "\xFF\xD8\xFF")
            || str_starts_with($bytes, 'GIF87a')
            || str_starts_with($bytes, 'GIF89a');
    }

    public static function householdScope(int $householdId): string
    {
        return 'household:'.$householdId;
    }

    public static function userScope(int $userId): string
    {
        return 'user:'.$userId;
    }

    private static function encrypter(string $scope): Encrypter
    {
        $key = hash('sha256', config('app.key').'|'.$scope, true);

        return new Encrypter($key, 'AES-256-CBC');
    }
}
