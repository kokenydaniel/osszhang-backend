<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2);
            $table->string('direction', 32);
            $table->date('settled_at');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['household_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_settlements');
    }
};
