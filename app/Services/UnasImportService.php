<?php

namespace App\Services;

use App\Integrations\Unas\UnasClient;
use App\Support\AccessControl;
use App\Support\FeatureFlags;

class UnasImportService
{
    public function __construct(
        private readonly UnasClient $client,
    ) {}

    public function import(User $user): array
    {
        if (! FeatureFlags::isEnabled('enable_unas_import')) {
            return ['success' => false, 'error' => 'Az UNAS import jelenleg ki van kapcsolva a platformon.', 'status' => 403];
        }

        if (! AccessControl::canUseFeature($user, 'shopify_import')) {
            return ['success' => false, 'error' => 'A webshop import Premium előfizetéssel érhető el.', 'status' => 403];
        }

        $household = $user->household;
        if (! $household?->unas_import_enabled) {
            return ['success' => false, 'error' => 'Az UNAS import nincs engedélyezve a háztartásban.', 'status' => 400];
        }

        if (! $household->unas_shop_id || ! $household->unas_api_key) {
            return ['success' => false, 'error' => 'Hiányzó UNAS hozzáférési adatok.', 'status' => 400];
        }

        $this->client->setCredentials($household->unas_shop_id, $household->unas_api_key);
        $orders = $this->client->getOrders();

        return [
            'success' => true,
            'message' => 'UNAS import előkészítve. Importált rendelések: '.count($orders),
            'imported' => count($orders),
            'status' => 200,
        ];
    }
}
