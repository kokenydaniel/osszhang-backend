<?php

namespace App\Services\Formatters;

use App\Models\BusinessOrder;
use App\Models\Household;

class BusinessOrderRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function businessOrderLegacy(BusinessOrder $o): array
    {
        return [
            'customer_name' => (string) $o->customer_name,
            'amount' => (float) $o->amount,
            'channel' => (string) $o->channel,
            'payment_method' => (string) $o->payment_method,
            'provider' => (string) $o->provider,
            'destination' => (string) $o->destination,
            'invoice_id' => $o->invoice_id,
        ];
    }

    public function businessOrderResolved(BusinessOrder $o, Household $household): array
    {
        return $this->resolve($household, $o->encrypted_payload, $this->businessOrderLegacy($o));
    }

    public function persistBusinessOrder(BusinessOrder $o, Household $household, array $sensitive): void
    {
        $this->persist($household, $o, $sensitive, [
            'customer_name' => '—',
            'amount' => 0,
            'channel' => '—',
            'payment_method' => '—',
            'provider' => '—',
            'destination' => '—',
            'invoice_id' => null,
        ]);
    }

    public function formatBusinessOrder(BusinessOrder $o, Household $household): array
    {
        $s = $this->businessOrderResolved($o, $household);

        return [
            'id' => $o->id,
            'customerName' => (string) ($s['customer_name'] ?? ''),
            'amount' => (float) ($s['amount'] ?? 0),
            'date' => $o->date,
            'channel' => (string) ($s['channel'] ?? ''),
            'paymentMethod' => (string) ($s['payment_method'] ?? ''),
            'provider' => (string) ($s['provider'] ?? ''),
            'destination' => (string) ($s['destination'] ?? ''),
            'paidDate' => $o->paid_date,
            'hasInvoice' => (bool) $o->has_invoice,
            'invoiceId' => $s['invoice_id'] ?? null,
            'state' => $o->state,
            'shopifyOrderId' => $o->shopify_order_id,
            'shopifyOrderNumber' => $o->shopify_order_number,
        ];
    }
}
