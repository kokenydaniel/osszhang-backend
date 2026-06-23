<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class LenientEncrypted implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            if ($this->looksLikeShopifyAdminToken($value)) {
                return $value;
            }

            return null;
        }
    }

    public function set($model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $plain = (string) $value;

        if ($this->looksLikeEncryptedPayload($plain)) {
            return $plain;
        }

        return Crypt::encryptString($plain);
    }

    private function looksLikeShopifyAdminToken(string $value): bool
    {
        return str_starts_with($value, 'shpat_') || str_starts_with($value, 'shpua_');
    }

    private function looksLikeEncryptedPayload(string $value): bool
    {
        return str_starts_with($value, 'eyJpdiI6') && strlen($value) > 40;
    }
}
