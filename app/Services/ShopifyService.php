<?php

namespace App\Services;

use App\Integrations\Shopify\ShopifyClient;

class ShopifyService
{
    public function __construct(
        private readonly ShopifyClient $client,
    ) {}

    public function setCredentials(?string $url, ?string $token): void
    {
        $this->client->setCredentials($url, $token);
    }

    public function getOrders(?string $sinceDate = null): array
    {
        return $this->client->getOrders($sinceDate);
    }
}
