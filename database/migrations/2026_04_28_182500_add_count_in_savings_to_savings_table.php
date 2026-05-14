<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->boolean('count_in_savings')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn('count_in_savings');
        });
    }
};
