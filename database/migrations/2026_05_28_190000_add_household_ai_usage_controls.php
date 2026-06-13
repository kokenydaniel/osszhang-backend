<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('ai_usage_blocked')->default(false)->after('tier_grant_granted_by');
            $table->unsignedInteger('ai_monthly_token_limit')->nullable()->after('ai_usage_blocked');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['ai_usage_blocked', 'ai_monthly_token_limit']);
        });
    }
};
