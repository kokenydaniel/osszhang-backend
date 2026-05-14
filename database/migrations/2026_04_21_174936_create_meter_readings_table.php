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
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('value', 15, 2);
            $table->boolean('is_reset')->default(false);
            $table->decimal('consumption', 15, 2)->default(0);
            $table->boolean('is_estimated')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
