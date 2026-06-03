<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->boolean('pocket_money_enabled')->default(false)->after('business_enabled');
            $table->boolean('insurance_enabled')->default(false)->after('pocket_money_enabled');
            $table->boolean('rental_enabled')->default(false)->after('insurance_enabled');

            $table->json('pocket_money_settings')->nullable()->after('dashboard_settings');
            $table->json('insurance_settings')->nullable()->after('pocket_money_settings');
            $table->json('rental_settings')->nullable()->after('insurance_settings');

            $table->boolean('woocommerce_import_enabled')->default(false)->after('shopify_import_enabled');
            $table->string('woocommerce_shop_url')->nullable()->after('shopify_shop_url');
            $table->text('woocommerce_consumer_key')->nullable()->after('woocommerce_shop_url');
            $table->text('woocommerce_consumer_secret')->nullable()->after('woocommerce_consumer_key');

            $table->boolean('unas_import_enabled')->default(false)->after('woocommerce_consumer_secret');
            $table->string('unas_shop_id')->nullable()->after('unas_import_enabled');
            $table->text('unas_api_key')->nullable()->after('unas_shop_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency', 8)->default('HUF')->after('amount');
        });

        Schema::table('business_orders', function (Blueprint $table) {
            $table->string('woocommerce_order_id')->nullable()->after('shopify_order_number');
            $table->string('unas_order_id')->nullable()->after('woocommerce_order_id');
            $table->string('external_source', 32)->nullable()->after('unas_order_id');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url');
            $table->string('secret', 128);
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 100);
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamps();
        });

        Schema::create('pocket_money_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('member_label');
            $table->enum('entry_type', ['allowance', 'expense', 'adjustment']);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('HUF');
            $table->date('entry_date');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('insurer')->nullable();
            $table->decimal('annual_premium', 15, 2)->default(0);
            $table->string('currency', 8)->default('HUF');
            $table->date('renewal_date')->nullable();
            $table->date('covered_until')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rental_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('monthly_rent', 15, 2)->default(0);
            $table->string('currency', 8)->default('HUF');
            $table->string('tenant_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rental_income_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_property_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('HUF');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('paid_date')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        $now = now();
        $flags = [
            ['enable_shopify_import', 'Shopify rendelés import'],
            ['enable_woocommerce_import', 'WooCommerce rendelés import'],
            ['enable_unas_import', 'UNAS rendelés import'],
            ['enable_attachments', 'Számla és nyugta csatolás'],
            ['enable_webhooks', 'Webhook-ok'],
            ['enable_audit_log', 'Audit napló'],
            ['enable_ai_weekly_briefing', 'Heti pénzügyi jelentés'],
            ['enable_ai_overspend', 'Miért ment el a pénz?'],
            ['enable_ai_auto_categorize', 'Kategória javaslat'],
            ['enable_ai_year_analysis', 'Éves költség-összefoglaló'],
            ['enable_ai_utility_anomaly', 'Rezsi figyelő'],
            ['enable_ai_debt_optimizer', 'Hitel visszafizetési terv'],
            ['enable_ai_business_strategy', 'Éves bevétel elemzés'],
            ['enable_ai_payment_priority', 'Mit fizessek előbb?'],
            ['enable_ai_vat_estimate', 'ÁFA kimutatás'],
            ['enable_ai_cost_reduction', 'Spórolási javaslatok'],
        ];

        foreach ($flags as [$key, $description]) {
            if (! DB::table('feature_flags')->where('key', $key)->exists()) {
                DB::table('feature_flags')->insert([
                    'key' => $key,
                    'value' => true,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('feature_flags')
            ->where('key', 'enable_ai_cfo')
            ->update(['description' => 'Havi pénzügyi tanácsadó — irányítópult widget.']);

        DB::table('feature_flags')
            ->where('key', 'enable_ai_travel_planner')
            ->update(['description' => 'Utazás költségtervező.']);
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_income_entries');
        Schema::dropIfExists('rental_properties');
        Schema::dropIfExists('insurance_policies');
        Schema::dropIfExists('pocket_money_entries');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');

        Schema::table('business_orders', function (Blueprint $table) {
            $table->dropColumn(['woocommerce_order_id', 'unas_order_id', 'external_source']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn([
                'pocket_money_enabled', 'insurance_enabled', 'rental_enabled',
                'pocket_money_settings', 'insurance_settings', 'rental_settings',
                'woocommerce_import_enabled', 'woocommerce_shop_url',
                'woocommerce_consumer_key', 'woocommerce_consumer_secret',
                'unas_import_enabled', 'unas_shop_id', 'unas_api_key',
            ]);
        });

        DB::table('feature_flags')->whereIn('key', [
            'enable_shopify_import', 'enable_woocommerce_import', 'enable_unas_import',
            'enable_attachments', 'enable_webhooks', 'enable_audit_log',
            'enable_ai_weekly_briefing', 'enable_ai_overspend', 'enable_ai_auto_categorize',
            'enable_ai_year_analysis', 'enable_ai_utility_anomaly', 'enable_ai_debt_optimizer',
            'enable_ai_business_strategy', 'enable_ai_payment_priority', 'enable_ai_vat_estimate',
            'enable_ai_cost_reduction',
        ])->delete();
    }
};
