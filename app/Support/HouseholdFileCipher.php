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
        } catch (DecryptException) {
            return $payload;
        }
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
