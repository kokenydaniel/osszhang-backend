<?php

namespace App\Support;

use App\Models\BusinessOrder;
use App\Models\Household;
use App\Services\EncryptedRecordService;

class BusinessTaxRevenue
{
    public static function orderQualifiesAsDocumented(BusinessOrder $order, array $resolved): bool
    {
        if ((float) $order->amount <= 0) {
            return false;
        }

        if ($order->has_invoice) {
            return true;
        }

        $invoiceId = trim((string) ($resolved['invoice_id'] ?? ''));
        if ($invoiceId === '') {
            return false;
        }

        if (self::isLikelyShopOrderReference($invoiceId, $order)) {
            return false;
        }

        return true;
    }

    public static function isLikelyShopOrderReference(string $invoiceId, BusinessOrder $order): bool
    {
        $id = trim($invoiceId);
        if ($id === '') {
            return true;
        }

        $shopNumber = trim((string) ($order->shopify_order_number ?? ''));
        if ($shopNumber !== '' && $id === $shopNumber) {
            return true;
        }

        if (preg_match('/^#\d+$/i', $id)) {
            return true;
        }

        if (preg_match('/^\d{4,}$/', $id) && ! preg_match('/[A-Za-z]/', $id)) {
            return true;
        }

        return false;
    }

    public static function countsAsRevenue(BusinessOrder $order, Household $household, EncryptedRecordService $crypto, string $revenueBasis): bool
    {
        if ((float) $order->amount <= 0) {
            return false;
        }

        if ($revenueBasis === 'all_orders') {
            return true;
        }

        $resolved = $crypto->businessOrderResolved($order, $household);

        return self::orderQualifiesAsDocumented($order, $resolved);
    }

    public static function toNetAmount(float $amount, array $biz): float
    {
        $taxRegime = (string) ($biz['tax_regime'] ?? 'aam');
        $vatPercent = $taxRegime === 'vat'
            ? max(0.0, min(100.0, (float) ($biz['default_vat_percent'] ?? 27)))
            : 0.0;
        $priceMode = ($biz['price_input_mode'] ?? 'gross') === 'net' ? 'net' : 'gross';

        if ($vatPercent <= 0 || $priceMode === 'net') {
            return round($amount, 2);
        }

        return round($amount / (1 + $vatPercent / 100), 2);
    }
}
