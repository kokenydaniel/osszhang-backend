<?php

namespace App\Integrations\Unas;

class UnasClient
{
    private ?string $shopId = null;

    private ?string $apiKey = null;

    public function setCredentials(string $shopId, string $apiKey): void
    {
        $this->shopId = trim($shopId);
        $this->apiKey = $apiKey;
    }

    public function getOrders(): array
    {

        if (! $this->shopId || ! $this->apiKey) {
            return [];
        }

        return [];
    }
}
