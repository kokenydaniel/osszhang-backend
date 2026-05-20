<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->text('sensitive_encrypted')->nullable()->after('cipher_key_encrypted');
        });

        Schema::table('savings', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('count_in_savings');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('reason');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('next_payout_date');
        });

        Schema::table('meter_readings', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('is_official');
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('location');
        });

        Schema::table('utility_settlements', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('sensitive_encrypted');
        });

        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('meter_readings', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('utility_settlements', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });
    }
};
