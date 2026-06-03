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

    /** @return list<array<string, mixed>> */
    public function getOrders(): array
    {
        // Előkészített integráció — UNAS API hívás ide kerül.
        if (! $this->shopId || ! $this->apiKey) {
            return [];
        }

        return [];
    }
}
