<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('business_enabled')->default(true);
            $table->string('business_name')->default('Little Loom');
            $table->string('shopify_shop_url')->nullable();
            $table->string('shopify_access_token')->nullable();
            $table->boolean('utility_split_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'business_enabled',
                'business_name',
                'shopify_shop_url',
                'shopify_access_token',
                'utility_split_enabled'
            ]);
        });
    }
};
