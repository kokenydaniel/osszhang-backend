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
            $table->decimal('goal_amount', 15, 2)->default(0)->after('count_in_savings');
            $table->decimal('current_amount', 15, 2)->default(0)->after('goal_amount');
            $table->date('target_date')->nullable()->after('current_amount');
        });

        DB::table('savings')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $ledgerSum = (float) DB::table('ledger_entries')
                    ->where('saving_id', $row->id)
                    ->sum('amount');

                DB::table('savings')
                    ->where('id', $row->id)
                    ->update([
                        'current_amount' => $ledgerSum,
                        'goal_amount' => $ledgerSum > 0 ? $ledgerSum : 0,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('savings', function (Blueprint $table) {
            $table->dropColumn(['goal_amount', 'current_amount', 'target_date']);
        });
    }
};
