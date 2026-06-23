<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->decimal('amount', 24, 8)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
        });
    }
};
