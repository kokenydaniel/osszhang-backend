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

    public function getOrders(): array
    {

        if (! $this->shopUrl || ! $this->consumerKey || ! $this->consumerSecret) {
            return [];
        }

        return [];
    }
}
