<?php

namespace App\Services;

use App\Integrations\WooCommerce\WooCommerceClient;
use App\Support\AccessControl;
use App\Support\FeatureFlags;

class WooCommerceImportService
{
    public function __construct(
        private readonly WooCommerceClient $client,
    ) {}

    public function import(User $user): array
    {
        if (! FeatureFlags::isEnabled('enable_woocommerce_import')) {
            return ['success' => false, 'error' => 'A WooCommerce import jelenleg ki van kapcsolva a platformon.', 'status' => 403];
        }

        if (! AccessControl::canUseFeature($user, 'shopify_import')) {
            return ['success' => false, 'error' => 'A webshop import Premium előfizetéssel érhető el.', 'status' => 403];
        }

        $household = $user->household;
        if (! $household?->woocommerce_import_enabled) {
            return ['success' => false, 'error' => 'A WooCommerce import nincs engedélyezve a háztartásban.', 'status' => 400];
        }

        if (! $household->woocommerce_shop_url || ! $household->woocommerce_consumer_key || ! $household->woocommerce_consumer_secret) {
            return ['success' => false, 'error' => 'Hiányzó WooCommerce hozzáférési adatok.', 'status' => 400];
        }

        $this->client->setCredentials(
            $household->woocommerce_shop_url,
            $household->woocommerce_consumer_key,
            $household->woocommerce_consumer_secret,
        );

        $orders = $this->client->getOrders();

        return [
            'success' => true,
            'message' => 'WooCommerce import előkészítve. Importált rendelések: '.count($orders),
            'imported' => count($orders),
            'status' => 200,
        ];
    }
}
