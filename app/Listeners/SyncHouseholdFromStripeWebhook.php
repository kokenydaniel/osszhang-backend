<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\HouseholdSubscriptionSync;
use Laravel\Cashier\Events\WebhookHandled;

class SyncHouseholdFromStripeWebhook
{
    /** @var list<string> */
    private const SYNC_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.payment_succeeded',
    ];

    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $type = $payload['type'] ?? '';

        if (! in_array($type, self::SYNC_EVENTS, true)) {
            return;
        }

        $customerId = $this->resolveCustomerId($payload);
        if ($customerId === null) {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();
        if ($user === null) {
            return;
        }

        HouseholdSubscriptionSync::syncUserHousehold($user);
    }

    /** @param array<string, mixed> $payload */
    private function resolveCustomerId(array $payload): ?string
    {
        $object = $payload['data']['object'] ?? [];

        if (isset($object['customer']) && is_string($object['customer'])) {
            return $object['customer'];
        }

        return null;
    }
}
