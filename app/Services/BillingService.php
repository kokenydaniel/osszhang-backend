<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccessControl;
use App\Support\HouseholdTierAccess;
use App\Support\PlatformSettings;
use App\Support\StripePlans;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;
use Laravel\Cashier\Subscription;
use Stripe\Card as StripeCard;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod as StripePaymentMethod;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BillingService
{
    /** @var list<string> Stripe currencies billed in the major unit (no /100). HUF is not included. */
    private const ZERO_DECIMAL_CURRENCIES = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
        'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    public function __construct(
        private readonly string $frontendUrl,
    ) {}

    public static function make(): self
    {
        return new self(rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/'));
    }

    /** @return array<string, mixed> */
    public function billingSummary(User $user): array
    {
        $user->load('household');

        if (PlatformSettings::isBetaMode()) {
            return $this->betaBillingSummary($user);
        }

        HouseholdSubscriptionSync::syncUserHousehold($user);
        $user->load('household');

        $household = $user->household;
        $billingTier = AccessControl::billingTier($user);
        $accessTier = AccessControl::effectiveTier($user);
        $dbStatus = $household?->subscription_status ?? AccessControl::STATUS_NONE;

        $subscription = $this->resolveSubscription($user);
        $subscriptionStatus = $this->resolveSubscriptionStatus($billingTier, $dbStatus, $subscription);

        $summary = in_array($billingTier, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)
            ? $this->paidSummary($user, $billingTier, $subscription)
            : $this->freeSummary($billingTier);

        $accessEndsAt = $subscriptionStatus === AccessControl::STATUS_CANCELED
            ? ($subscription?->ends_at?->toDateString() ?? $summary['nextBillingDate'] ?? null)
            : null;

        $tierGrant = HouseholdTierAccess::grantPayload($household);

        return array_merge($summary, [
            'billingTier' => $billingTier,
            'billing_tier' => $billingTier,
            'accessTier' => $accessTier,
            'access_tier' => $accessTier,
            'tierGrant' => $tierGrant,
            'tier_grant' => $tierGrant,
            'subscriptionStatus' => $subscriptionStatus,
            'subscription_status' => $subscriptionStatus,
            'cancelAtPeriodEnd' => $subscriptionStatus === AccessControl::STATUS_CANCELED,
            'cancel_at_period_end' => $subscriptionStatus === AccessControl::STATUS_CANCELED,
            'pendingDowngradeTier' => null,
            'pending_downgrade_tier' => null,
            'accessEndsAt' => $accessEndsAt,
            'access_ends_at' => $accessEndsAt,
        ]);
    }

    public function downloadInvoice(User $user, string $invoiceId): SymfonyResponse
    {
        $this->ensureBillingAdmin($user);

        return $user->downloadInvoice($invoiceId);
    }

    /** @return array{url: string} */
    public function createCheckoutSession(User $user, string $priceId): array
    {
        $this->ensureBillingAdmin($user);
        $this->ensureBillingEnabled();

        if (! StripePlans::isAllowedPriceId($priceId)) {
            throw ValidationException::withMessages([
                'price_id' => ['Érvénytelen ár azonosító.'],
            ]);
        }

        $checkout = $user->checkout([$priceId], [
            'mode' => 'subscription',
            'success_url' => $this->billingReturnUrl('success=true'),
            'cancel_url' => $this->billingReturnUrl('canceled=true'),
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'household_id' => (string) ($user->household_id ?? ''),
                ],
            ],
        ], [
            'email' => $this->customerEmail($user),
            'name' => trim($user->first_name.' '.$user->last_name),
        ]);

        return ['url' => (string) $checkout->url];
    }

    /** @return array{url: string} */
    public function createPortalSession(User $user): array
    {
        $this->ensureBillingAdmin($user);
        $this->ensureBillingEnabled();

        if ($user->stripe_id === null) {
            throw ValidationException::withMessages([
                'subscription' => ['Még nincs Stripe ügyfél fiókod. Először válassz csomagot.'],
            ]);
        }

        return [
            'url' => $user->billingPortalUrl($this->billingReturnUrl()),
        ];
    }

    private function ensureBillingAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            throw new AuthorizationException('Csak a háztartás adminisztrátora kezelheti az előfizetést.');
        }

        if ($user->household_id === null) {
            throw ValidationException::withMessages([
                'household' => ['Nincs háztartás a felhasználóhoz rendelve.'],
            ]);
        }
    }

    private function ensureBillingEnabled(): void
    {
        if (PlatformSettings::isBetaMode()) {
            throw ValidationException::withMessages([
                'subscription' => ['A számlázás béta módban ki van kapcsolva — minden funkció szabadon elérhető.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function betaBillingSummary(User $user): array
    {
        $tier = AccessControl::effectiveTier($user);
        $household = $user->household;
        $dbStatus = $household?->subscription_status ?? AccessControl::STATUS_NONE;

        $summary = in_array($tier, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)
            ? [
                'effectiveTier' => $tier,
                'effective_tier' => $tier,
                'nextBillingDate' => null,
                'next_billing_date' => null,
                'billingAmount' => 'Béta mód — ingyenes hozzáférés',
                'billing_amount' => 'Béta mód — ingyenes hozzáférés',
                'upcomingInvoice' => null,
                'upcoming_invoice' => null,
                'paymentMethod' => null,
                'payment_method' => null,
                'invoices' => [],
            ]
            : $this->freeSummary($tier);

        return array_merge($summary, [
            'subscriptionStatus' => $dbStatus,
            'subscription_status' => $dbStatus,
            'cancelAtPeriodEnd' => false,
            'cancel_at_period_end' => false,
            'betaMode' => true,
            'beta_mode' => true,
            'pendingDowngradeTier' => null,
            'pending_downgrade_tier' => null,
            'accessEndsAt' => null,
            'access_ends_at' => null,
        ]);
    }

    private function billingReturnUrl(string $query = ''): string
    {
        $base = $this->frontendUrl.'/settings?tab=billing';

        return $query !== '' ? $base.'&'.$query : $base;
    }

    private function customerEmail(User $user): ?string
    {
        if (filter_var($user->username, FILTER_VALIDATE_EMAIL)) {
            return $user->username;
        }

        return null;
    }

    private function resolveSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()->orderByDesc('created_at')->first();
    }

    private function resolveSubscriptionStatus(string $tier, string $dbStatus, ?Subscription $subscription): string
    {
        if (! in_array($tier, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)) {
            return AccessControl::STATUS_NONE;
        }

        if ($subscription !== null && $subscription->canceled() && $subscription->onGracePeriod()) {
            return AccessControl::STATUS_CANCELED;
        }

        if ($subscription !== null && $subscription->canceled()) {
            return AccessControl::STATUS_CANCELED;
        }

        if ($dbStatus === AccessControl::STATUS_CANCELED) {
            return AccessControl::STATUS_CANCELED;
        }

        if ($subscription !== null && in_array($subscription->stripe_status, ['past_due'], true)) {
            return AccessControl::STATUS_PAST_DUE;
        }

        if ($subscription !== null && $subscription->stripe_status === 'trialing') {
            return AccessControl::STATUS_TRIALING;
        }

        return AccessControl::STATUS_ACTIVE;
    }

    /** @return array<string, mixed> */
    private function freeSummary(string $tier): array
    {
        return [
            'effectiveTier' => $tier,
            'effective_tier' => $tier,
            'nextBillingDate' => null,
            'next_billing_date' => null,
            'billingAmount' => '0 Ft',
            'billing_amount' => '0 Ft',
            'paymentMethod' => null,
            'payment_method' => null,
            'upcomingInvoice' => null,
            'upcoming_invoice' => null,
            'invoices' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function paidSummary(User $user, string $tier, ?Subscription $subscription): array
    {
        $nextBillingDate = null;
        $billingAmount = $tier === AccessControl::TIER_PREMIUM ? 'Premium' : 'Pro';
        $upcomingInvoice = $this->upcomingInvoiceSummary($user, $subscription);

        if ($subscription !== null) {
            try {
                $stripeSubscription = $subscription->asStripeSubscription();
                if (isset($stripeSubscription->current_period_end)) {
                    $nextBillingDate = date('Y-m-d', $stripeSubscription->current_period_end);
                }

                $priceId = $subscription->stripe_price ?? $subscription->items()->value('stripe_price');
                if ($priceId !== null) {
                    $price = Cashier::stripe()->prices->retrieve($priceId, ['expand' => ['product']]);
                    $billingAmount = $this->formatStripePrice($price);
                }
            } catch (ApiErrorException $exception) {
                Log::warning('Stripe subscription lookup failed', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($upcomingInvoice !== null) {
            $nextBillingDate = $upcomingInvoice['date'] ?? $nextBillingDate;
        }

        return [
            'effectiveTier' => $tier,
            'effective_tier' => $tier,
            'nextBillingDate' => $nextBillingDate,
            'next_billing_date' => $nextBillingDate,
            'billingAmount' => $billingAmount,
            'billing_amount' => $billingAmount,
            'upcomingInvoice' => $upcomingInvoice,
            'upcoming_invoice' => $upcomingInvoice,
            'paymentMethod' => $this->paymentMethodSummary($user),
            'payment_method' => $this->paymentMethodSummarySnake($user),
            'invoices' => $this->invoiceHistory($user, $tier),
        ];
    }

    /** @return array{date: string|null, amount: string}|null */
    private function upcomingInvoiceSummary(User $user, ?Subscription $subscription = null): ?array
    {
        if ($user->stripe_id === null) {
            return null;
        }

        if ($subscription !== null && $subscription->canceled()) {
            return null;
        }

        try {
            $upcoming = $user->upcomingInvoice();
            if ($upcoming === null) {
                return null;
            }

            $currency = (string) ($upcoming->invoice->currency ?? 'huf');
            $amountValue = $this->stripeAmount($upcoming->rawTotal(), $currency);

            $date = null;
            if (isset($upcoming->invoice->period_end)) {
                $date = date('Y-m-d', $upcoming->invoice->period_end);
            } elseif ($upcoming->dueDate() !== null) {
                $date = $upcoming->dueDate()->toDateString();
            } else {
                $date = $upcoming->date()->toDateString();
            }

            return [
                'date' => $date,
                'amount' => $this->formatMoney($amountValue),
            ];
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe upcoming invoice lookup failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function paymentMethodSummary(User $user): ?array
    {
        if ($user->stripe_id === null) {
            return null;
        }

        try {
            return $this->resolveCardDetails($user);
        } catch (\Throwable $exception) {
            Log::warning('Stripe payment method lookup failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array{brand: string, last4: string, expMonth: int, expYear: int}|null */
    private function resolveCardDetails(User $user): ?array
    {
        foreach ($this->paymentMethodCandidates($user) as $candidate) {
            $summary = $this->cardDetailsFromCandidate($candidate);
            if ($summary !== null) {
                return $summary;
            }
        }

        if ($user->pm_type && $user->pm_last_four) {
            return [
                'brand' => (string) $user->pm_type,
                'last4' => (string) $user->pm_last_four,
                'expMonth' => 0,
                'expYear' => 0,
            ];
        }

        return null;
    }

    /** @return list<mixed> */
    private function paymentMethodCandidates(User $user): array
    {
        $candidates = [];

        try {
            $default = $user->defaultPaymentMethod();
            if ($default !== null) {
                $candidates[] = $default;
            }
        } catch (\Throwable) {
            // Stripe customer default may be unset after Checkout — fall back below.
        }

        foreach ($user->paymentMethods('card') as $method) {
            $candidates[] = $method;
        }

        $subscription = $user->subscription() ?? $user->subscriptions()->first();
        if ($subscription !== null) {
            try {
                $stripeSub = $subscription->asStripeSubscription();
                $paymentMethod = $stripeSub->default_payment_method ?? null;

                if (is_string($paymentMethod) && $paymentMethod !== '') {
                    $candidates[] = Cashier::stripe()->paymentMethods->retrieve($paymentMethod);
                } elseif ($paymentMethod instanceof StripePaymentMethod) {
                    $candidates[] = $paymentMethod;
                }
            } catch (ApiErrorException) {
                //
            }
        }

        return $candidates;
    }

    /** @return array{brand: string, last4: string, expMonth: int, expYear: int}|null */
    private function cardDetailsFromCandidate(mixed $candidate): ?array
    {
        if ($candidate instanceof CashierPaymentMethod) {
            $candidate = $candidate->asStripePaymentMethod();
        }

        if ($candidate instanceof StripePaymentMethod && isset($candidate->card)) {
            return [
                'brand' => (string) ($candidate->card->brand ?? 'card'),
                'last4' => (string) ($candidate->card->last4 ?? '0000'),
                'expMonth' => (int) ($candidate->card->exp_month ?? 0),
                'expYear' => (int) ($candidate->card->exp_year ?? 0),
            ];
        }

        if ($candidate instanceof StripeCard) {
            return [
                'brand' => (string) ($candidate->brand ?? 'card'),
                'last4' => (string) ($candidate->last4 ?? '0000'),
                'expMonth' => (int) ($candidate->exp_month ?? 0),
                'expYear' => (int) ($candidate->exp_year ?? 0),
            ];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function paymentMethodSummarySnake(User $user): ?array
    {
        $method = $this->paymentMethodSummary($user);
        if ($method === null) {
            return null;
        }

        return [
            'brand' => $method['brand'],
            'last4' => $method['last4'],
            'exp_month' => $method['expMonth'],
            'exp_year' => $method['expYear'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function invoiceHistory(User $user, string $tier): array
    {
        if ($user->stripe_id === null) {
            return [];
        }

        try {
            return collect($user->invoices(false, ['limit' => 5]))
                ->map(function ($invoice) use ($tier) {
                    $currency = (string) ($invoice->currency ?? 'huf');
                    $amount = $this->formatMoney(
                        $this->stripeAmount((int) ($invoice->rawTotal() ?? 0), $currency),
                    );
                    $status = $invoice->isPaid() ? 'paid' : (string) ($invoice->invoice->status ?? 'open');

                    return [
                        'id' => (string) $invoice->id,
                        'date' => $invoice->date()->toDateString(),
                        'planLabel' => $tier === AccessControl::TIER_PREMIUM ? 'Premium' : 'Pro',
                        'plan_label' => $tier === AccessControl::TIER_PREMIUM ? 'Premium' : 'Pro',
                        'amount' => $amount,
                        'status' => $status,
                        'statusLabel' => $this->invoiceStatusLabel($status),
                        'status_label' => $this->invoiceStatusLabel($status),
                        'pdfUrl' => $invoice->invoice_pdf,
                        'pdf_url' => $invoice->invoice_pdf,
                        'downloadUrl' => url('/api/subscription/invoices/'.$invoice->id.'/download'),
                        'download_url' => url('/api/subscription/invoices/'.$invoice->id.'/download'),
                    ];
                })
                ->values()
                ->all();
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe invoice lookup failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function formatStripePrice(object $price): string
    {
        $amount = $this->stripeAmount($price->unit_amount ?? 0, (string) ($price->currency ?? 'huf'));
        $formatted = $this->formatMoney($amount);
        $interval = $price->recurring->interval ?? null;

        return match ($interval) {
            'month' => $formatted.' / hó',
            'year' => $formatted.' / év',
            default => $formatted,
        };
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }

    private function invoiceStatusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'Fizetve',
            'open' => 'Nyitott',
            'draft' => 'Piszkozat',
            'uncollectible' => 'Behajthatatlan',
            'void' => 'Érvénytelen',
            default => ucfirst($status),
        };
    }

    private function stripeAmount(int $amount, string $currency): int
    {
        $currency = strtolower($currency);

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return $amount;
        }

        return (int) round($amount / 100);
    }
}
