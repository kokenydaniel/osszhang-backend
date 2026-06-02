<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('budget_settings')->nullable()->after('categories');
            $table->json('utilities_settings')->nullable()->after('utility_templates');
            $table->json('dashboard_settings')->nullable()->after('meters_settings');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['budget_settings', 'utilities_settings', 'dashboard_settings']);
        });
    }
};
