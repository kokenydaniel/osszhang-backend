<?php

use App\Support\BusinessSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->json('business_settings')->nullable()->after('business_name');
        });

        DB::table('households')->whereNull('business_settings')->update([
            'business_settings' => json_encode(BusinessSettings::defaults()),
        ]);
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('business_settings');
        });
    }
};
