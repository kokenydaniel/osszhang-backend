<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('budget_enabled')->default(false)->after('manual_balance');
            $table->boolean('savings_enabled')->default(false)->after('budget_enabled');
            $table->boolean('debts_enabled')->default(false)->after('savings_enabled');
            $table->boolean('utilities_enabled')->default(false)->after('debts_enabled');
            $table->boolean('meters_enabled')->default(false)->after('utilities_enabled');
        });

        DB::table('households')->update([
            'budget_enabled' => true,
            'savings_enabled' => true,
            'debts_enabled' => true,
            'utilities_enabled' => true,
            'meters_enabled' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'budget_enabled',
                'savings_enabled',
                'debts_enabled',
                'utilities_enabled',
                'meters_enabled',
            ]);
        });
    }
};
