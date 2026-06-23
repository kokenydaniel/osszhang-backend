<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('savings') || Schema::hasColumn('savings', 'travel_plan_id')) {
            return;
        }

        Schema::table('savings', function (Blueprint $table) {
            $table->foreignId('travel_plan_id')->nullable()->after('wallet_id')->constrained('travel_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('savings') || ! Schema::hasColumn('savings', 'travel_plan_id')) {
            return;
        }

        Schema::table('savings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_plan_id');
        });
    }
};
