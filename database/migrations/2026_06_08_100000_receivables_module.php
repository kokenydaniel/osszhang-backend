<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('receivables_enabled')->default(false)->after('rental_enabled');
        });

        Schema::create('receivable_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['household_id', 'name']);
        });

        Schema::create('receivable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receivable_contact_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 16);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('HUF');
            $table->string('source', 24);
            $table->date('entry_date');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['receivable_contact_id', 'entry_date']);
            $table->index(['household_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_entries');
        Schema::dropIfExists('receivable_contacts');

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('receivables_enabled');
        });
    }
};
