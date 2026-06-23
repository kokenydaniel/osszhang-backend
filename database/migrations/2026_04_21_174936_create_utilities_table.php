<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('utilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->decimal('total', 15, 2);
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('paid_by')->nullable();
            $table->enum('split_rule', ['shared', 'dani-private', 'ildi-private'])->default('shared');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilities');
    }
};
