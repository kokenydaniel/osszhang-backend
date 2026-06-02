<?php

namespace App\Services;

use App\Models\User;
use App\Support\AccessControl;
use App\Support\StripePlans;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiErrorException;

class HouseholdSubscriptionSync
{
    public static function syncUserHousehold(User $user): void
    {
        self::refreshSubscriptionFromStripe($user);

        $household = $user->household;
        if ($household === null) {
            return;
        }

        $subscription = self::resolveSubscription($user);

        if ($subscription === null || ! self::subscriptionGrantsAccess($subscription)) {
            // Csak Stripe ügyfeleknél nullázunk — kézi DB / admin grant nélküli tier megmarad.
            if ($user->stripe_id !== null) {
                $household->update([
                    'subscription_tier' => AccessControl::TIER_FREE,
                    'subscription_status' => AccessControl::STATUS_NONE,
                ]);
            }

            return;
        }

        $priceId = $subscription->stripe_price
            ?? $subscription->items()->value('stripe_price');

        $tier = StripePlans::tierForPriceId($priceId) ?? AccessControl::TIER_FREE;

        $household->update([
            'subscription_tier' => $tier,
            'subscription_status' => self::subscriptionIsCanceled($subscription)
                ? AccessControl::STATUS_CANCELED
                : AccessControl::STATUS_ACTIVE,
        ]);
    }

    public static function refreshSubscriptionFromStripe(User $user): void
    {
        $subscription = $user->subscriptions()->orderByDesc('created_at')->first();
        if ($subscription === null) {
            return;
        }

        try {
            $stripeSubscription = $subscription->asStripeSubscription();
            $subscription->stripe_status = $stripeSubscription->status;

            if ($stripeSubscription->cancel_at_period_end ?? false) {
                $subscription->ends_at = Carbon::createFromTimestamp(
                    $stripeSubscription->cancel_at ?? $stripeSubscription->current_period_end,
                );
            } elseif (isset($stripeSubscription->cancel_at)) {
                $subscription->ends_at = Carbon::createFromTimestamp($stripeSubscription->cancel_at);
            } elseif ($stripeSubscription->status === 'canceled') {
                $subscription->ends_at = $subscription->ends_at ?? Carbon::createFromTimestamp(
                    $stripeSubscription->ended_at ?? $stripeSubscription->current_period_end ?? time(),
                );
            } elseif ($stripeSubscription->status === 'active') {
                $subscription->ends_at = null;
            }

            $subscription->save();
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe subscription refresh failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function subscriptionIsCanceled(Subscription $subscription): bool
    {
        return $subscription->canceled() && $subscription->onGracePeriod();
    }

    private static function resolveSubscription(User $user): ?Subscription
    {
        $active = $user->subscriptions()
            ->whereIn('stripe_status', ['active', 'trialing', 'past_due'])
            ->orderByDesc('created_at')
            ->first();

        if ($active !== null) {
            return $active;
        }

        return $user->subscriptions()
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->first();
    }

    private static function subscriptionGrantsAccess(Subscription $subscription): bool
    {
        if ($subscription->active()) {
            return true;
        }

        if ($subscription->onGracePeriod()) {
            return true;
        }

        return in_array($subscription->stripe_status, ['active', 'trialing', 'past_due'], true);
    }
}
