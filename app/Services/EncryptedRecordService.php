<?php

namespace App\Services;

use App\Models\BusinessOrder;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Investment;
use App\Models\LedgerEntry;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\Saving;
use App\Models\Utility;
use App\Models\UtilitySettlement;

/**
 * Háztartásonkénti titkosítás — az adatbázisban csak blob + semleges placeholder látszik.
 */
class EncryptedRecordService
{
    public function __construct(
        private readonly HouseholdCipherService $cipher,
    ) {}

    public function ensureKey(Household $household): void
    {
        $this->cipher->ensureCipherKey($household);
    }

    private function decrypt(Household $household, ?string $blob): ?array
    {
        if (! $blob) {
            return null;
        }

        try {
            return $this->cipher->decrypt($household, $blob);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolve(Household $household, ?string $blob, array $legacy): array
    {
        return $this->decrypt($household, $blob) ?? $legacy;
    }

    private function persist(Household $household, object $model, array $sensitive, array $masked): void
    {
        $this->ensureKey($household);
        $model->encrypted_payload = $this->cipher->encrypt($household, $sensitive);
        foreach ($masked as $key => $value) {
            $model->{$key} = $value;
        }
    }

    // —— Household (manual_balance, utility_templates) ——

    public function householdSensitive(Household $household): array
    {
        $legacy = [
            'manual_balance' => (float) $household->manual_balance,
            'utility_templates' => $household->resolvedUtilityTemplates(),
        ];

        return $this->resolve($household, $household->sensitive_encrypted ?? null, $legacy);
    }

    public function persistHouseholdSensitive(Household $household, array $sensitive): void
    {
        $this->ensureKey($household);
        $household->sensitive_encrypted = $this->cipher->encrypt($household, $sensitive);
        $household->manual_balance = 0;
        $household->utility_templates = [];
    }

    public function resolvedManualBalance(Household $household): float
    {
        return (float) ($this->householdSensitive($household)['manual_balance'] ?? 0);
    }

    public function adjustManualBalance(Household $household, float $delta): void
    {
        $sensitive = $this->householdSensitive($household);
        $sensitive['manual_balance'] = (float) ($sensitive['manual_balance'] ?? 0) + $delta;
        $this->persistHouseholdSensitive($household, $sensitive);
        $household->saveQuietly();
    }

    // —— Utility ——

    public function utilityLegacy(Utility $u): array
    {
        return [
            'type' => (string) $u->type,
            'total' => (float) $u->total,
            'paid_by' => $u->paid_by,
            'split_rule' => (string) ($u->split_rule ?? 'shared'),
        ];
    }

    public function utilityResolved(Utility $u, Household $household): array
    {
        return $this->resolve($household, $u->encrypted_payload, $this->utilityLegacy($u));
    }

    public function persistUtility(Utility $u, Household $household, array $sensitive): void
    {
        $this->persist($household, $u, $sensitive, [
            'type' => '—',
            'total' => 0,
            'paid_by' => null,
            'split_rule' => 'shared',
        ]);
    }

    public function formatUtility(Utility $u, Household $household): array
    {
        $s = $this->utilityResolved($u, $household);

        return [
            'id' => $u->id,
            'type' => (string) ($s['type'] ?? ''),
            'total' => (float) ($s['total'] ?? 0),
            'dueDate' => $u->due_date,
            'paidDate' => $u->paid_date,
            'paidBy' => $s['paid_by'] ?? null,
            'splitRule' => (string) ($s['split_rule'] ?? 'shared'),
        ];
    }

    // —— Utility settlement ——

    public function settlementLegacy(UtilitySettlement $s): array
    {
        return [
            'amount' => (float) $s->amount,
            'direction' => (string) $s->direction,
        ];
    }

    public function settlementResolved(UtilitySettlement $s, Household $household): array
    {
        return $this->resolve($household, $s->encrypted_payload, $this->settlementLegacy($s));
    }

    public function persistSettlement(UtilitySettlement $s, Household $household, array $sensitive): void
    {
        $this->persist($household, $s, $sensitive, [
            'amount' => 0,
            'direction' => $sensitive['direction'] ?? 'partner_pays_household',
        ]);
    }

    // —— Debt ——

    public function debtLegacy(Debt $d): array
    {
        return [
            'name' => (string) $d->name,
            'target_amount' => (float) $d->target_amount,
            'paid_amount' => (float) $d->paid_amount,
            'annual_interest_rate' => $d->annual_interest_rate !== null ? (float) $d->annual_interest_rate : null,
            'minimum_payment' => $d->minimum_payment !== null ? (float) $d->minimum_payment : null,
            'due_day' => $d->due_day !== null ? (int) $d->due_day : null,
            'status' => (string) $d->status,
        ];
    }

    public function debtResolved(Debt $d, Household $household): array
    {
        return $this->resolve($household, $d->encrypted_payload, $this->debtLegacy($d));
    }

    public function persistDebt(Debt $d, Household $household, array $sensitive): void
    {
        $this->persist($household, $d, $sensitive, [
            'name' => '—',
            'target_amount' => 0,
            'paid_amount' => 0,
            'annual_interest_rate' => null,
            'minimum_payment' => null,
            'due_day' => null,
            'status' => 'Még fizetendő',
        ]);
    }

    public function formatDebt(Debt $d, Household $household): array
    {
        $s = $this->debtResolved($d, $household);

        return [
            'id' => $d->id,
            'name' => (string) ($s['name'] ?? ''),
            'targetAmount' => (float) ($s['target_amount'] ?? 0),
            'paidAmount' => (float) ($s['paid_amount'] ?? 0),
            'annualInterestRate' => isset($s['annual_interest_rate']) ? (float) $s['annual_interest_rate'] : null,
            'minimumPayment' => isset($s['minimum_payment']) ? (float) $s['minimum_payment'] : null,
            'dueDay' => isset($s['due_day']) ? (int) $s['due_day'] : null,
            'status' => (string) ($s['status'] ?? ''),
        ];
    }

    // —— Saving + ledger ——

    public function savingLegacy(Saving $saving): array
    {
        return [
            'institution' => (string) $saving->institution,
            'currency' => (string) $saving->currency,
            'owner' => (string) $saving->owner,
        ];
    }

    public function savingResolved(Saving $saving, Household $household): array
    {
        return $this->resolve($household, $saving->encrypted_payload, $this->savingLegacy($saving));
    }

    public function persistSaving(Saving $saving, Household $household, array $sensitive): void
    {
        $this->persist($household, $saving, $sensitive, [
            'institution' => '—',
            'currency' => '—',
            'owner' => '—',
        ]);
    }

    public function ledgerLegacy(LedgerEntry $entry): array
    {
        return [
            'amount' => (float) $entry->amount,
            'reason' => (string) $entry->reason,
        ];
    }

    public function ledgerResolved(LedgerEntry $entry, Household $household): array
    {
        return $this->resolve($household, $entry->encrypted_payload, $this->ledgerLegacy($entry));
    }

    public function persistLedger(LedgerEntry $entry, Household $household, array $sensitive): void
    {
        $this->persist($household, $entry, $sensitive, [
            'amount' => 0,
            'reason' => '—',
        ]);
    }

    public function formatSaving(Saving $saving, Household $household): array
    {
        $s = $this->savingResolved($saving, $household);
        $ledger = $saving->relationLoaded('ledger')
            ? $saving->ledger->map(fn (LedgerEntry $e) => $this->formatLedgerEntry($e, $household))->values()->all()
            : [];

        return [
            'id' => $saving->id,
            'institution' => (string) ($s['institution'] ?? ''),
            'currency' => (string) ($s['currency'] ?? ''),
            'owner' => (string) ($s['owner'] ?? ''),
            'count_in_savings' => (bool) $saving->count_in_savings,
            'ledger' => $ledger,
        ];
    }

    public function formatLedgerEntry(LedgerEntry $entry, Household $household): array
    {
        $s = $this->ledgerResolved($entry, $household);

        return [
            'id' => $entry->id,
            'date' => $entry->date,
            'amount' => (float) ($s['amount'] ?? 0),
            'reason' => (string) ($s['reason'] ?? ''),
            'saving_id' => $entry->saving_id,
            'transaction_id' => $entry->transaction_id,
        ];
    }

    // —— Investment ——

    public function investmentLegacy(Investment $i): array
    {
        return [
            'name' => (string) $i->name,
            'type' => (string) $i->type,
            'principal_amount' => (float) $i->principal_amount,
            'annual_interest_rate' => (float) $i->annual_interest_rate,
            'owner' => (string) $i->owner,
            'current_value' => $i->current_value !== null ? (float) $i->current_value : null,
            'maturity_amount' => $i->maturity_amount !== null ? (float) $i->maturity_amount : null,
            'next_payout_amount' => $i->next_payout_amount !== null ? (float) $i->next_payout_amount : null,
        ];
    }

    public function investmentResolved(Investment $i, Household $household): array
    {
        return $this->resolve($household, $i->encrypted_payload, $this->investmentLegacy($i));
    }

    public function persistInvestment(Investment $i, Household $household, array $sensitive): void
    {
        $this->persist($household, $i, $sensitive, [
            'name' => '—',
            'type' => '—',
            'principal_amount' => 0,
            'annual_interest_rate' => 0,
            'owner' => '—',
            'current_value' => null,
            'maturity_amount' => null,
            'next_payout_amount' => null,
        ]);
    }

    public function formatInvestment(Investment $i, Household $household): array
    {
        $s = $this->investmentResolved($i, $household);

        return [
            'id' => $i->id,
            'name' => (string) ($s['name'] ?? ''),
            'type' => (string) ($s['type'] ?? 'bond'),
            'principalAmount' => (float) ($s['principal_amount'] ?? 0),
            'annualInterestRate' => (float) ($s['annual_interest_rate'] ?? 0),
            'purchaseDate' => $i->purchase_date->toDateString(),
            'maturityDate' => $i->maturity_date ? $i->maturity_date->toDateString() : null,
            'owner' => (string) ($s['owner'] ?? 'Közös'),
            'countInSavings' => (bool) $i->count_in_savings,
            'currentValue' => isset($s['current_value']) ? (float) $s['current_value'] : null,
            'maturityAmount' => isset($s['maturity_amount']) ? (float) $s['maturity_amount'] : null,
            'nextPayoutAmount' => isset($s['next_payout_amount']) ? (float) $s['next_payout_amount'] : null,
            'nextPayoutDate' => $i->next_payout_date ? $i->next_payout_date->toDateString() : null,
        ];
    }

    // —— Meter ——

    public function meterLegacy(Meter $m): array
    {
        return [
            'name' => (string) $m->name,
            'location' => (string) $m->location,
        ];
    }

    public function meterResolved(Meter $m, Household $household): array
    {
        return $this->resolve($household, $m->encrypted_payload, $this->meterLegacy($m));
    }

    public function persistMeter(Meter $m, Household $household, array $sensitive): void
    {
        $this->persist($household, $m, $sensitive, [
            'name' => '—',
            'location' => '—',
        ]);
    }

    public function readingLegacy(MeterReading $r): array
    {
        return [
            'value' => (float) $r->value,
            'consumption' => (float) $r->consumption,
        ];
    }

    public function readingResolved(MeterReading $r, Household $household): array
    {
        return $this->resolve($household, $r->encrypted_payload, $this->readingLegacy($r));
    }

    public function persistReading(MeterReading $r, Household $household, array $sensitive): void
    {
        $this->persist($household, $r, $sensitive, [
            'value' => 0,
            'consumption' => 0,
        ]);
    }

    public function formatMeter(Meter $m, Household $household): array
    {
        $s = $this->meterResolved($m, $household);
        $readings = $m->relationLoaded('readings')
            ? $m->readings->map(fn (MeterReading $r) => $this->formatReading($r, $household))->values()->all()
            : [];

        return [
            'id' => $m->id,
            'name' => (string) ($s['name'] ?? ''),
            'unit' => $m->unit,
            'location' => (string) ($s['location'] ?? ''),
            'icon' => $m->icon,
            'readings' => $readings,
        ];
    }

    public function formatReading(MeterReading $r, Household $household): array
    {
        $s = $this->readingResolved($r, $household);

        return [
            'id' => $r->id,
            'meter_id' => $r->meter_id,
            'value' => (float) ($s['value'] ?? 0),
            'consumption' => (float) ($s['consumption'] ?? 0),
            'date' => $r->date,
            'month' => $r->month,
            'year' => $r->year,
            'is_reset' => (bool) $r->is_reset,
            'is_estimated' => (bool) $r->is_estimated,
            'is_official' => (bool) $r->is_official,
        ];
    }

    // —— Business order ——

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
