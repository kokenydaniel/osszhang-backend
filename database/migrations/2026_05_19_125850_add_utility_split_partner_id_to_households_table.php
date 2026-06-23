<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->unsignedBigInteger('utility_split_partner_id')->nullable();
            $table->foreign('utility_split_partner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropForeign(['utility_split_partner_id']);
            $table->dropColumn('utility_split_partner_id');
        });
    }
};
