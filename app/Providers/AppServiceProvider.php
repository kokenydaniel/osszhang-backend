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
        Event::listen(WebhookHandled::class, SyncHouseholdFromStripeWebhook::class);

        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Saving::class, SavingPolicy::class);
        Gate::policy(Debt::class, DebtPolicy::class);
    }
}
