<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_shared')->default(true);
            $table->timestamps();

            $table->index(['household_id', 'is_shared']);
        });

        $driver = Schema::getConnection()->getDriverName();
        $sharedTrue = $driver === 'pgsql' ? 'true' : '1';

        DB::statement(
            "CREATE UNIQUE INDEX wallets_one_shared_per_household ON wallets (household_id) WHERE is_shared = {$sharedTrue}"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
