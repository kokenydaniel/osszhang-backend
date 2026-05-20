<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('utility_templates')->nullable()->after('utility_split_partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('utility_templates');
        });
    }
};
