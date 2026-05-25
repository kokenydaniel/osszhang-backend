<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->string('type', 16)->default('account')->after('wallet_id');
        });

        DB::table('savings')->update([
            'type' => 'account',
            'goal_amount' => 0,
            'target_date' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
