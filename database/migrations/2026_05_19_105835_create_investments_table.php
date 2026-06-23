<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default('bond');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('annual_interest_rate', 5, 2);
            $table->date('purchase_date');
            $table->date('maturity_date')->nullable();
            $table->string('owner')->nullable();
            $table->boolean('count_in_savings')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
