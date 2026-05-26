<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('value')->default(false);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('feature_flags')->insert([
            [
                'key' => 'maintenance_mode',
                'value' => false,
                'description' => 'Karbantartási mód — az alkalmazás használata korlátozható minden felhasználó számára.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'global_ai_search',
                'value' => true,
                'description' => 'Globális AI keresés — platform szintű AI funkciók elérhetősége.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flags');
    }
};
