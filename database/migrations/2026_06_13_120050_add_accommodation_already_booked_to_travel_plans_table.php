<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('travel_plans') || Schema::hasColumn('travel_plans', 'accommodation_already_booked')) {
            return;
        }

        Schema::table('travel_plans', function (Blueprint $table) {
            $table->boolean('accommodation_already_booked')->default(false)->after('transport_already_booked');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('travel_plans') || ! Schema::hasColumn('travel_plans', 'accommodation_already_booked')) {
            return;
        }

        Schema::table('travel_plans', function (Blueprint $table) {
            $table->dropColumn('accommodation_already_booked');
        });
    }
};
