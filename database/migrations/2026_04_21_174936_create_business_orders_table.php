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
        Schema::create('business_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('customer_name');
            $table->string('channel');
            $table->string('payment_method');
            $table->string('provider');
            $table->string('destination');
            $table->decimal('amount', 15, 2);
            $table->date('paid_date')->nullable();
            $table->boolean('has_invoice')->default(false);
            $table->string('invoice_id')->nullable();
            $table->enum('state', ['RENDBEN', 'KINT', 'KINT_PARKOL'])->default('KINT');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_orders');
    }
};
