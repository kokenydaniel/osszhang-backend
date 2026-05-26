<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $betaStored = DB::table('app_settings')->where('key', 'beta_mode')->value('value');
        $betaEnabled = filter_var($betaStored, FILTER_VALIDATE_BOOLEAN);
        $now = now();

        DB::table('feature_flags')->updateOrInsert(
            ['key' => 'beta_mode'],
            [
                'value' => $betaEnabled,
                'description' => 'Béta mód — tier korlátozások és Stripe számlázás kikapcsolása minden háztartásra.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('feature_flags')->where('key', 'beta_mode')->delete();
    }
};
