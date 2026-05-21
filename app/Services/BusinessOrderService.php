<?php

namespace App\Services;

use App\Models\BusinessOrder;
use App\Models\Household;
use App\Support\BusinessSettings;

class BusinessOrderService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function listForHousehold(Household $household): array
    {
        return BusinessOrder::where('household_id', $household->id)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($o) => $this->crypto->formatBusinessOrder($o, $household))
            ->all();
    }

    public function create(Household $household, array $validated): array
    {
        $biz = $household->resolvedBusinessSettings() ?? BusinessSettings::defaults();

        $o = new BusinessOrder([
            'household_id' => $household->id,
            'date' => $validated['date'],
            'paid_date' => $validated['paidDate'] ?? null,
            'state' => ($validated['paidDate'] ?? null) ? 'RENDBEN' : 'KINT',
        ]);
        $this->crypto->persistBusinessOrder($o, $household, [
            'customer_name' => $validated['customerName'],
            'amount' => (float) $validated['amount'],
            'channel' => $validated['channel'] ?? ($biz['channels'][0] ?? 'Webshop'),
            'payment_method' => $validated['paymentMethod'] ?? ($biz['payment_methods'][0] ?? 'Kártya'),
            'provider' => $validated['provider'] ?? ($biz['providers'][0] ?? 'Nincs'),
            'destination' => $validated['destination'] ?? ($biz['destinations'][0] ?? 'Szolgáltatónál parkol'),
            'invoice_id' => $validated['invoiceId'] ?? null,
        ]);
        $o->save();

        return $this->crypto->formatBusinessOrder($o, $household);
    }

    public function update(Household $household, BusinessOrder $businessOrder, array $validated, array $input): array
    {
        $sensitive = $this->crypto->businessOrderResolved($businessOrder, $household);
        if (array_key_exists('customerName', $validated)) {
            $sensitive['customer_name'] = $validated['customerName'];
        }
        if (array_key_exists('amount', $validated)) {
            $sensitive['amount'] = (float) $validated['amount'];
        }
        if (array_key_exists('channel', $input)) {
            $sensitive['channel'] = $input['channel'];
        }
        if (array_key_exists('paymentMethod', $input)) {
            $sensitive['payment_method'] = $input['paymentMethod'];
        }
        if (array_key_exists('provider', $input)) {
            $sensitive['provider'] = $input['provider'];
        }
        if (array_key_exists('destination', $input)) {
            $sensitive['destination'] = $input['destination'];
        }
        if (array_key_exists('invoiceId', $input)) {
            $sensitive['invoice_id'] = $input['invoiceId'];
        }
        if (array_key_exists('date', $validated)) {
            $businessOrder->date = $validated['date'];
        }
        $businessOrder->paid_date = $input['paidDate'] ?? $businessOrder->paid_date;
        $businessOrder->state = $input['state'] ?? ($businessOrder->paid_date ? 'RENDBEN' : 'KINT');

        $this->crypto->persistBusinessOrder($businessOrder, $household, $sensitive);
        $businessOrder->save();

        return $this->crypto->formatBusinessOrder($businessOrder, $household);
    }

    public function delete(BusinessOrder $businessOrder): void
    {
        $businessOrder->delete();
    }
}
