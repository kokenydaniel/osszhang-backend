<?php

namespace App\Providers;

use App\Models\Debt;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Policies\DebtPolicy;
use App\Policies\SavingPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\WalletPolicy;
use App\Listeners\SyncHouseholdFromStripeWebhook;
use App\Services\BillingService;
use App\Support\StorageDisk;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Events\WebhookHandled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillingService::class, fn () => BillingService::make());
    }

    public function boot(): void
    {
        config(['filesystems.default' => StorageDisk::default()]);

        if ($this->app->environment('production') && ! StorageDisk::objectStorageConfigured()) {
            logger()->warning(
                'Object storage (Supabase S3) is not configured. Set SUPABASE_STORAGE_* Fly secrets — uploads will not persist.',
            );
        }

        Event::listen(WebhookHandled::class, SyncHouseholdFromStripeWebhook::class);

        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Saving::class, SavingPolicy::class);
        Gate::policy(Debt::class, DebtPolicy::class);
    }
}
