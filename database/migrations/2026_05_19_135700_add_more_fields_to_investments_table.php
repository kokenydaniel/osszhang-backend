<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('maturity_amount', 15, 2)->nullable()->after('current_value');
            $table->decimal('next_payout_amount', 15, 2)->nullable()->after('maturity_amount');
            $table->date('next_payout_date')->nullable()->after('next_payout_amount');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['maturity_amount', 'next_payout_amount', 'next_payout_date']);
        });
    }
};
