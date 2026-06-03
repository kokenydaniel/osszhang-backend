<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_properties', function (Blueprint $table) {
            $table->decimal('monthly_common_cost', 15, 2)->default(0)->after('monthly_rent');
            $table->date('contract_ends_at')->nullable()->after('tenant_name');
        });

        Schema::table('rental_income_entries', function (Blueprint $table) {
            $table->unique(
                ['rental_property_id', 'period_year', 'period_month'],
                'rental_income_property_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('rental_income_entries', function (Blueprint $table) {
            $table->dropUnique('rental_income_property_period_unique');
        });

        Schema::table('rental_properties', function (Blueprint $table) {
            $table->dropColumn(['monthly_common_cost', 'contract_ends_at']);
        });
    }
};
