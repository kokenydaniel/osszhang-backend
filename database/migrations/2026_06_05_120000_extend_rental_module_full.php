<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_properties', function (Blueprint $table) {
            $table->unsignedTinyInteger('due_day')->default(5)->after('tenant_name');
            $table->text('notes')->nullable()->after('due_day');
            $table->text('agreement_notes')->nullable()->after('notes');
            $table->boolean('budget_sync_enabled')->default(false)->after('is_active');
        });

        Schema::table('rental_income_entries', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('period_month');
            $table->decimal('rent_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('common_cost_amount', 15, 2)->default(0)->after('rent_amount');
        });

        Schema::create('rental_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_property_id')->constrained()->cascadeOnDelete();
            $table->string('expense_type', 32);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('HUF');
            $table->date('expense_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_expenses');

        Schema::table('rental_income_entries', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'rent_amount', 'common_cost_amount']);
        });

        Schema::table('rental_properties', function (Blueprint $table) {
            $table->dropColumn(['due_day', 'notes', 'agreement_notes', 'budget_sync_enabled']);
        });
    }
};
