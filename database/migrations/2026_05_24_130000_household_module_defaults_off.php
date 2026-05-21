<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE households ALTER COLUMN business_enabled SET DEFAULT false');
            DB::statement("ALTER TABLE households ALTER COLUMN business_name SET DEFAULT ''");
            DB::statement('ALTER TABLE households ALTER COLUMN utility_split_enabled SET DEFAULT false');
        } else {
            DB::statement('ALTER TABLE households MODIFY business_enabled TINYINT(1) NOT NULL DEFAULT 0');
            DB::statement("ALTER TABLE households MODIFY business_name VARCHAR(255) NOT NULL DEFAULT ''");
            DB::statement('ALTER TABLE households MODIFY utility_split_enabled TINYINT(1) NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE households ALTER COLUMN business_enabled SET DEFAULT true');
            DB::statement("ALTER TABLE households ALTER COLUMN business_name SET DEFAULT 'Little Loom'");
            DB::statement('ALTER TABLE households ALTER COLUMN utility_split_enabled SET DEFAULT true');
        } else {
            DB::statement('ALTER TABLE households MODIFY business_enabled TINYINT(1) NOT NULL DEFAULT 1');
            DB::statement("ALTER TABLE households MODIFY business_name VARCHAR(255) NOT NULL DEFAULT 'Little Loom'");
            DB::statement('ALTER TABLE households MODIFY utility_split_enabled TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
};
