<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    protected ?string $baseUrl = null;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.shopify.url');
        $this->accessToken = config('services.shopify.token');
    }

    /**
     * Dynamically override Shopify credentials.
     */
    public function setCredentials(?string $url, ?string $token)
    {
        $this->baseUrl = $url;
        $this->accessToken = $token;
    }

    /**
     * Fetch orders from Shopify for a specific time range.
     */
    public function getOrders(string $sinceDate = null)
    {
        if (!$this->baseUrl || !$this->accessToken) {
            throw new \Exception("Shopify configuration missing (URL or Token).");
        }

        // Shopify Admin API endpoint
        $url = "https://{$this->baseUrl}/admin/api/2024-01/orders.json?status=any&limit=250";
        
        if ($sinceDate) {
            $url .= "&created_at_min=" . urlencode($sinceDate);
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->get($url);

        if ($response->failed()) {
            Log::error("Shopify API Error: " . $response->body());
            throw new \Exception("Failed to fetch orders from Shopify: " . $response->status());
        }

        return $response->json('orders');
    }
}
