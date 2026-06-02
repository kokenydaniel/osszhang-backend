<?php

namespace App\Services;

use App\Models\BusinessOrder;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\BusinessSettings;
use Carbon\Carbon;

class ShopifyImportService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
        private readonly ShopifyService $shopify,
    ) {}

    public function import(User $user): array
    {
        if (! AccessControl::canUseFeature($user, 'shopify_import')) {
            return [
                'success' => false,
                'error' => AccessControl::featureAccessDeniedMessage('shopify_import'),
                'status' => 403,
            ];
        }

        $household = $user->household;
        $biz = $household ? $household->resolvedBusinessSettings() : BusinessSettings::defaults();

        if (! $household || ! $household->shopify_import_enabled) {
            return [
                'success' => false,
                'error' => 'A Shopify import nincs engedélyezve. Kapcsold be a Beállítások → Modulok menüpontban.',
                'status' => 400,
            ];
        }

        if (! $household->shopify_shop_url || ! $household->shopify_access_token) {
            return [
                'success' => false,
                'error' => 'Nincsenek beállítva Shopify hozzáférési adatok ehhez a háztartáshoz!',
                'status' => 400,
            ];
        }

        $this->shopify->setCredentials($household->shopify_shop_url, $household->shopify_access_token);

        $shopifyOrders = $this->shopify->getOrders();

        $importedCount = 0;
        $householdId = $user->household_id;

        foreach ($shopifyOrders as $so) {
            $orderId = (string) ($so['id']);
            $orderNumber = $so['name'] ?? $orderId;

            $exists = BusinessOrder::where('household_id', $householdId)
                ->where(function ($q) use ($orderId, $orderNumber) {
                    $q->where('shopify_order_id', $orderId)
                        ->orWhere('invoice_id', $orderNumber);
                })
                ->exists();

            if ($exists) {
                continue;
            }

            $gateways = $so['payment_gateway_names'] ?? [];
            $method = $biz['payment_methods'][0] ?? 'Kártya';

            $isCod = false;
            foreach ($gateways as $g) {
                $gl = strtolower($g);
                if (str_contains($gl, 'cod') || str_contains($gl, 'manual') || str_contains($gl, 'Cash on Delivery') || str_contains($gl, 'utánvét')) {
                    $isCod = true;
                    break;
                }
            }
            if ($isCod) {
                $cod = collect($biz['payment_methods'])->first(
                    fn ($m) => stripos($m, 'utánvét') !== false || stripos($m, 'utanvet') !== false
                );
                $method = $cod ?? 'Utánvét';
            }

            $invoiceId = $this->detectInvoiceId($so);

            $destination = $biz['destinations'][0] ?? 'Szolgáltatónál parkol';
            $state = 'KINT_PARKOL';

            $providerLabel = count($gateways) > 0
                ? implode(', ', $gateways)
                : ($biz['providers'][0] ?? 'Nincs');

            if ($so['financial_status'] !== 'paid') {
                $state = 'KINT';
            }

            $o = new BusinessOrder([
                'household_id' => $householdId,
                'date' => Carbon::parse($so['created_at'])->toDateString(),
                'paid_date' => $so['financial_status'] === 'paid' ? Carbon::parse($so['processed_at'])->toDateString() : null,
                'shopify_order_id' => $orderId,
                'shopify_order_number' => $orderNumber,
                'state' => $state,
                'order_status' => $biz['order_statuses'][0] ?? 'Függőben',
            ]);
            $this->crypto->persistBusinessOrder($o, $household, [
                'customer_name' => ($so['customer']['first_name'] ?? '').' '.($so['customer']['last_name'] ?? 'Vásárló'),
                'amount' => (float) $so['total_price'],
                'channel' => BusinessSettings::shopifyChannelLabel($biz),
                'payment_method' => $method,
                'provider' => $providerLabel,
                'destination' => $destination,
                'invoice_id' => $invoiceId,
            ]);
            $o->save();
            $importedCount++;
        }

        return [
            'success' => true,
            'imported_count' => $importedCount,
            'total_fetched' => count($shopifyOrders),
            'status' => 200,
        ];
    }

    private function detectInvoiceId(array $so): ?string
    {
        $noteAttribs = $so['note_attributes'] ?? [];
        foreach ($noteAttribs as $attr) {
            if (str_contains($attr['value'] ?? '', 'E-LL-')) {
                return $attr['value'];
            }
        }

        $tags = $so['tags'] ?? '';
        $tagArr = explode(',', $tags);
        foreach ($tagArr as $tag) {
            $t = trim($tag);
            if (str_starts_with($t, 'E-LL-')) {
                return $t;
            }
        }

        if (str_contains($so['note'] ?? '', 'E-LL-')) {
            preg_match('/E-LL-[0-9-]+/', $so['note'], $matches);

            return $matches[0] ?? null;
        }

        return null;
    }
}
