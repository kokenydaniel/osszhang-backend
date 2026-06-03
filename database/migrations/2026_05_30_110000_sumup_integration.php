<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('sumup_import_enabled')->default(false)->after('unas_import_enabled');
            $table->string('sumup_merchant_code', 32)->nullable()->after('sumup_import_enabled');
            $table->text('sumup_api_key')->nullable()->after('sumup_merchant_code');
        });

        Schema::table('business_documents', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('label');
            $table->string('import_key', 128)->nullable()->after('source');
            $table->index(['household_id', 'year', 'month', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('business_documents', function (Blueprint $table) {
            $table->dropIndex(['household_id', 'year', 'month', 'source']);
            $table->dropColumn(['source', 'import_key']);
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['sumup_import_enabled', 'sumup_merchant_code', 'sumup_api_key']);
        });
    }
};
