<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback_report_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_feedback_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 16);
            $table->text('body');
            $table->timestamps();

            $table->index(['user_feedback_report_id', 'created_at']);
        });

        Schema::table('user_feedback_reports', function (Blueprint $table) {
            $table->timestamp('user_last_read_at')->nullable()->after('status');
            $table->timestamp('admin_last_read_at')->nullable()->after('user_last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_feedback_reports', function (Blueprint $table) {
            $table->dropColumn(['user_last_read_at', 'admin_last_read_at']);
        });

        Schema::dropIfExists('user_feedback_report_messages');
    }
};
