<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->text('cipher_key_encrypted')->nullable()->after('utility_split_partner_id');
            $table->dropColumn(['encryption_enabled', 'encryption_salt', 'wrapped_dek']);
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('cipher_key_encrypted');
            $table->boolean('encryption_enabled')->default(false);
            $table->string('encryption_salt', 64)->nullable();
            $table->text('wrapped_dek')->nullable();
        });
    }
};
