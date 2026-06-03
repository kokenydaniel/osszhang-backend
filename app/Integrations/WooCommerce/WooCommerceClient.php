<?php

namespace App\Integrations\WooCommerce;

class WooCommerceClient
{
    private ?string $shopUrl = null;

    private ?string $consumerKey = null;

    private ?string $consumerSecret = null;

    public function setCredentials(string $shopUrl, string $consumerKey, string $consumerSecret): void
    {
        $this->shopUrl = rtrim($shopUrl, '/');
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
    }

    /** @return list<array<string, mixed>> */
    public function getOrders(): array
    {
        // Előkészített integráció — WooCommerce REST API hívás ide kerül.
        if (! $this->shopUrl || ! $this->consumerKey || ! $this->consumerSecret) {
            return [];
        }

        return [];
    }
}
