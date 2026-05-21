<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('savings_settings')->nullable()->after('meters_enabled');
            $table->json('debts_settings')->nullable()->after('savings_settings');
            $table->json('meters_settings')->nullable()->after('debts_settings');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['savings_settings', 'debts_settings', 'meters_settings']);
        });
    }
};
