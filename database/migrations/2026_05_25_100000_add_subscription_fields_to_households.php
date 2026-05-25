<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('subscription_tier', 32)->default('free')->after('onboarding_completed');
            $table->string('subscription_status', 32)->default('none')->after('subscription_tier');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['subscription_tier', 'subscription_status']);
        });
    }
};
