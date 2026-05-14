<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->decimal('annual_interest_rate', 5, 2)->nullable()->after('paid_amount');
            $table->decimal('minimum_payment', 15, 2)->nullable()->after('annual_interest_rate');
            $table->unsignedTinyInteger('due_day')->nullable()->after('minimum_payment');
        });
    }

    public function down(): void
    {
        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn(['annual_interest_rate', 'minimum_payment', 'due_day']);
        });
    }
};

