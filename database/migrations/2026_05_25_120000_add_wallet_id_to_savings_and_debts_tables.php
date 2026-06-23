<?php

use App\Models\Household;
use App\Services\WalletProvisioningService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('household_id')
                ->constrained()
                ->restrictOnDelete();
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->foreignId('wallet_id')
                ->nullable()
                ->after('household_id')
                ->constrained()
                ->restrictOnDelete();
        });

        $provisioning = app(WalletProvisioningService::class);

        Household::query()->orderBy('id')->chunkById(100, function ($households) use ($provisioning) {
            foreach ($households as $household) {
                $wallet = $provisioning->ensureSharedWallet($household);

                DB::table('savings')
                    ->where('household_id', $household->id)
                    ->whereNull('wallet_id')
                    ->update(['wallet_id' => $wallet->id]);

                DB::table('debts')
                    ->where('household_id', $household->id)
                    ->whereNull('wallet_id')
                    ->update(['wallet_id' => $wallet->id]);
            }
        });

        DB::statement('ALTER TABLE savings ALTER COLUMN wallet_id SET NOT NULL');
        DB::statement('ALTER TABLE debts ALTER COLUMN wallet_id SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_id');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wallet_id');
        });
    }
};
