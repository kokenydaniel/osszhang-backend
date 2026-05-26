<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('feature_flags')->insert([
            [
                'key' => 'enable_ai_cfo',
                'value' => false,
                'description' => 'AI CFO pénzügyi asszisztens — irányítópult widget és pénzügyi tanácsadás.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'enable_ai_travel_planner',
                'value' => false,
                'description' => 'AI utazástervező — okos eszközök menüpont és utazás-tervező modul.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('feature_flags')
            ->whereIn('key', ['enable_ai_cfo', 'enable_ai_travel_planner'])
            ->delete();
    }
};
