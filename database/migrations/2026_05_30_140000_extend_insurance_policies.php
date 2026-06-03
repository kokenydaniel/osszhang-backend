<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->string('policy_kind', 32)->default('general')->after('insurer');
            $table->decimal('fund_value', 15, 2)->nullable()->after('annual_premium');
            $table->boolean('premium_free')->default(false)->after('fund_value');
            $table->string('payment_frequency', 16)->default('annual')->after('premium_free');
            $table->decimal('payment_amount', 15, 2)->default(0)->after('payment_frequency');
            $table->boolean('budget_sync_enabled')->default(false)->after('notes');
            $table->unsignedSmallInteger('budget_start_year')->nullable()->after('budget_sync_enabled');
            $table->unsignedTinyInteger('budget_start_month')->nullable()->after('budget_start_year');
            $table->unsignedTinyInteger('budget_due_day')->nullable()->after('budget_start_month');
            $table->json('paid_budget_periods')->nullable()->after('budget_due_day');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_policies', function (Blueprint $table) {
            $table->dropColumn([
                'policy_kind',
                'fund_value',
                'premium_free',
                'payment_frequency',
                'payment_amount',
                'budget_sync_enabled',
                'budget_start_year',
                'budget_start_month',
                'budget_due_day',
                'paid_budget_periods',
            ]);
        });
    }
};
