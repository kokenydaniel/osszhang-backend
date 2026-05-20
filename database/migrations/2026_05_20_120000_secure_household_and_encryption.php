<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->text('shopify_access_token')->nullable()->change();
            $table->boolean('encryption_enabled')->default(false)->after('utility_split_partner_id');
            $table->string('encryption_salt', 64)->nullable()->after('encryption_enabled');
            $table->text('wrapped_dek')->nullable()->after('encryption_salt');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('is_reserve');
        });

        Schema::table('business_orders', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('amount');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('paid_amount');
        });

        Schema::table('utilities', function (Blueprint $table) {
            $table->text('encrypted_payload')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('shopify_access_token', 255)->nullable()->change();
            $table->dropColumn(['encryption_enabled', 'encryption_salt', 'wrapped_dek']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });

        Schema::table('utilities', function (Blueprint $table) {
            $table->dropColumn('encrypted_payload');
        });
    }
};
