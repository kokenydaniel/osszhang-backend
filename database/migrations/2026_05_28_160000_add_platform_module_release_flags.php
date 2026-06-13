<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $modules = [
            'budget' => 'Költségvetés modul — mindig elérhető.',
            'savings' => 'Megtakarítás modul platform szintű kiadása.',
            'debts' => 'Tartozások modul platform szintű kiadása.',
            'utilities' => 'Rezsi modul platform szintű kiadása.',
            'meters' => 'Közműórák modul platform szintű kiadása.',
            'business' => 'Vállalkozás modul platform szintű kiadása.',
            'pocket_money' => 'Zsebpénz modul platform szintű kiadása.',
            'insurance' => 'Biztosítások modul platform szintű kiadása.',
            'rental' => 'Bérbeadás modul platform szintű kiadása.',
            'receivables' => 'Kintlévőség modul platform szintű kiadása.',
            'travel_planner' => 'Utazástervező modul platform szintű kiadása.',
        ];

        $travelAiEnabled = (bool) DB::table('feature_flags')
            ->where('key', 'enable_ai_travel_planner')
            ->value('value');

        foreach ($modules as $moduleId => $description) {
            $key = "enable_module_{$moduleId}";
            if (DB::table('feature_flags')->where('key', $key)->exists()) {
                continue;
            }

            $value = match ($moduleId) {
                'budget' => true,
                'travel_planner' => $travelAiEnabled,
                default => true,
            };

            DB::table('feature_flags')->insert([
                'key' => $key,
                'value' => $value,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('feature_flags')
            ->where('key', 'like', 'enable_module_%')
            ->delete();
    }
};
