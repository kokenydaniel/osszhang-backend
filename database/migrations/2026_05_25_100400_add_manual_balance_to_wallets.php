<?php

use App\Models\Household;
use App\Models\Wallet;
use App\Services\EncryptedRecordService;
use App\Services\WalletProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('manual_balance', 15, 2)->default(0)->after('is_shared');
        });

        $crypto = app(EncryptedRecordService::class);
        $provisioning = app(WalletProvisioningService::class);

        Household::query()->orderBy('id')->chunkById(100, function ($households) use ($crypto, $provisioning) {
            foreach ($households as $household) {
                $sharedWallet = $provisioning->ensureSharedWallet($household);
                $legacyBalance = $crypto->resolvedManualBalance($household);

                if ((float) $sharedWallet->manual_balance === 0.0 && $legacyBalance !== 0.0) {
                    $sharedWallet->update(['manual_balance' => $legacyBalance]);
                }

                Wallet::query()
                    ->where('household_id', $household->id)
                    ->where('is_shared', false)
                    ->where('manual_balance', 0)
                    ->update(['manual_balance' => 0]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('manual_balance');
        });
    }
};
