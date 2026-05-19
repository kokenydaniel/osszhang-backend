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
            $table->string('name'); // pl. PMÁP 2030/J
            $table->string('type')->default('bond'); // bond, stock, stb.
            $table->decimal('principal_amount', 15, 2); // tőke
            $table->decimal('annual_interest_rate', 5, 2); // százalékban, pl. 18.5
            $table->date('purchase_date');
            $table->date('maturity_date')->nullable();
            $table->string('owner')->nullable(); // Közös, Szandi, Dani stb.
            $table->boolean('count_in_savings')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
