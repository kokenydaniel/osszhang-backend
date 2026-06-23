<?php

namespace App\Services;

use App\Models\Household;
use Illuminate\Support\Facades\Crypt;

class HouseholdCipherService
{
    public function ensureCipherKey(Household $household): void
    {
        if (! empty($household->cipher_key_encrypted)) {
            return;
        }

        $household->cipher_key_encrypted = Crypt::encryptString(base64_encode(random_bytes(32)));
        $household->saveQuietly();
    }

    private function rawKey(Household $household): string
    {
        $this->ensureCipherKey($household);
        $decoded = base64_decode(Crypt::decryptString($household->cipher_key_encrypted), true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new \RuntimeException('Érvénytelen háztartási titkosítási kulcs.');
        }

        return $decoded;
    }

    public function encrypt(Household $household, array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $key = $this->rawKey($household);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Titkosítás sikertelen.');
        }

        return base64_encode($iv.$tag.$cipher);
    }

    public function decrypt(Household $household, string $blob): array
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new \RuntimeException('Érvénytelen titkosított adat.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $key = $this->rawKey($household);
        $json = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($json === false) {
            throw new \RuntimeException('Visszafejtés sikertelen.');
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
