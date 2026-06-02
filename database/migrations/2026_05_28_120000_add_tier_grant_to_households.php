<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('tier_grant', 32)->nullable()->after('subscription_status');
            $table->timestamp('tier_grant_expires_at')->nullable()->after('tier_grant');
            $table->text('tier_grant_note')->nullable()->after('tier_grant_expires_at');
            $table->foreignId('tier_grant_granted_by')->nullable()->after('tier_grant_note')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tier_grant_granted_by');
            $table->dropColumn(['tier_grant', 'tier_grant_expires_at', 'tier_grant_note']);
        });
    }
};
