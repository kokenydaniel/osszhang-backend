<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('utilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->decimal('total', 15, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('paid_by')->nullable(); // 'Mi' | 'Ildi'
            $table->enum('split_rule', ['shared', 'dani-private', 'ildi-private'])->default('shared');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};
