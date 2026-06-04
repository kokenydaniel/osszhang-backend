<?php

namespace Tests\Unit;

use App\Support\HouseholdFileCipher;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Tests\TestCase;

class HouseholdFileCipherTest extends TestCase
{
    public function test_encrypt_decrypt_round_trip_preserves_pdf_bytes(): void
    {
        $scope = HouseholdFileCipher::householdScope(42);
        $pdf = '%PDF-1.4 test document bytes';

        $encrypted = HouseholdFileCipher::encrypt($scope, $pdf);
        $decrypted = HouseholdFileCipher::decrypt($scope, $encrypted);

        $this->assertSame($pdf, $decrypted);
    }

    public function test_decrypt_does_not_return_ciphertext_when_key_is_wrong(): void
    {
        $scopeA = HouseholdFileCipher::householdScope(1);
        $scopeB = HouseholdFileCipher::householdScope(2);
        $encrypted = HouseholdFileCipher::encrypt($scopeA, '%PDF-1.4');

        $this->expectException(DecryptException::class);
        HouseholdFileCipher::decrypt($scopeB, $encrypted);
    }

    public function test_detects_laravel_encrypted_payload(): void
    {
        $scope = HouseholdFileCipher::householdScope(9);
        $encrypted = HouseholdFileCipher::encrypt($scope, 'secret');

        $this->assertTrue(HouseholdFileCipher::looksLikeEncryptedPayload($encrypted));
        $this->assertFalse(HouseholdFileCipher::looksLikeEncryptedPayload('%PDF-1.0'));
    }
}
