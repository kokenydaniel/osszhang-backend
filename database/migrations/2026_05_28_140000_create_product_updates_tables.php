<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_updates', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body');
            $table->json('bullets')->nullable();
            $table->string('location_hint')->nullable();
            $table->string('kind', 24)->default('new');
            $table->string('module_id', 64)->nullable();
            $table->string('required_tier', 16)->nullable();
            $table->string('audience_role', 16)->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_href')->nullable();
            $table->string('hero_icon', 64)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('product_update_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_update_id')->constrained()->cascadeOnDelete();
            $table->timestamp('dismissed_at');
            $table->unique(['user_id', 'product_update_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_update_dismissals');
        Schema::dropIfExists('product_updates');
    }
};
