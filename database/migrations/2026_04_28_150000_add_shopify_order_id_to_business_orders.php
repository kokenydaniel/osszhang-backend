<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_orders', function (Blueprint $table) {
            $table->string('shopify_order_id')->nullable()->after('invoice_id');
            $table->string('shopify_order_number')->nullable()->after('shopify_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropColumn(['shopify_order_id', 'shopify_order_number']);
        });
    }
};
