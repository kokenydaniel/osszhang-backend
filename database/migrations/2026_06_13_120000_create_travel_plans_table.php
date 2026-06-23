<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('saving_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination');
            $table->string('origin_location')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedSmallInteger('travelers_count')->default(1);
            $table->decimal('total_budget', 14, 2);
            $table->date('target_date')->nullable();
            $table->string('trip_style', 32)->default('mixed');
            $table->string('accommodation_preference', 32)->default('mixed');
            $table->string('transport_mode', 32)->default('mixed');
            $table->boolean('transport_already_booked')->default(false);
            $table->boolean('accommodation_already_booked')->default(false);
            $table->decimal('car_fuel_consumption_l100', 5, 2)->nullable();
            $table->json('plan_payload');
            $table->json('input_payload')->nullable();
            $table->json('financial_context')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_plans');
    }
};
