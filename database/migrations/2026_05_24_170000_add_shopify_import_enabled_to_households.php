<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('shopify_import_enabled')->default(false)->after('business_name');
        });

        // Meglévő Shopify-konfigurációval rendelkező háztartásoknál tartsuk bekapcsolva az importot
        DB::table('households')
            ->whereNotNull('shopify_shop_url')
            ->where('shopify_shop_url', '!=', '')
            ->whereNotNull('shopify_access_token')
            ->where('shopify_access_token', '!=', '')
            ->update(['shopify_import_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('shopify_import_enabled');
        });
    }
};
