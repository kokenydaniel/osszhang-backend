<?php

namespace App\Services\Finance;

use App\Models\Household;
use App\Models\Investment;
use App\Models\Saving;
use App\Models\User;
use App\Models\Wallet;
use App\Services\EncryptedRecordService;
use App\Support\HufConverter;
use App\Support\SavingsSettings;
use Carbon\Carbon;

class TravelEligibleSavingsCalculator
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function compute(User $user, Wallet $wallet, Household $household, ?array $exchangeRates = null): array
    {
        $converter = new HufConverter($exchangeRates);
        $settings = SavingsSettings::resolve($household->savings_settings ?? null);
        $separateOwner = trim((string) ($settings['separate_owner'] ?? ''));

        $total = 0.0;
        $count = 0;
        $items = [];
        $countInSavingsTotal = 0.0;
        $excludedItems = [];

        $savings = Saving::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->with('ledger')
            ->get();

        foreach ($savings as $saving) {
            if (! $saving->count_in_savings) {
                continue;
            }

            $formatted = $this->crypto->formatSaving($saving, $household);
            $currency = $this->savingCurrency($formatted);
            $nativeAmount = $this->savingBalanceNative($saving, $formatted);
            if ($nativeAmount <= 0) {
                continue;
            }

            $amountHuf = round($converter->toHuf($nativeAmount, $currency), 2);
            $item = $this->buildSavingItem($formatted, $saving, $nativeAmount, $currency, $amountHuf);

            $countInSavingsTotal += $amountHuf;
            $exclusionReason = $this->travelExclusionReason($formatted, $separateOwner, isInvestment: false);
            if ($exclusionReason !== null) {
                $item['reason'] = $exclusionReason;
                $excludedItems[] = $item;

                continue;
            }

            $total += $amountHuf;
            $count++;
            $items[] = $item;
        }

        $investments = Investment::query()
            ->where('household_id', $household->id)
            ->get();

        foreach ($investments as $investment) {
            if (! $investment->count_in_savings) {
                continue;
            }

            $formatted = $this->crypto->formatInvestment($investment, $household);
            $nativeAmount = $this->investmentValueHuf($investment, $formatted);
            if ($nativeAmount <= 0) {
                continue;
            }

            $amountHuf = round($nativeAmount, 2);
            $item = [
                'id' => 'investment-'.$investment->id,
                'label' => (string) ($formatted['name'] ?? 'Befektetés'),
                'amount_huf' => $amountHuf,
                'amount_native' => round($nativeAmount, 2),
                'currency' => 'HUF',
                'kind' => 'investment',
            ];

            $countInSavingsTotal += $amountHuf;
            $exclusionReason = $this->travelExclusionReason($formatted, $separateOwner, isInvestment: true);
            if ($exclusionReason !== null) {
                $item['reason'] = $exclusionReason;
                $excludedItems[] = $item;

                continue;
            }

            $total += $amountHuf;
            $count++;
            $items[] = $item;
        }

        usort($items, fn (array $a, array $b) => $b['amount_huf'] <=> $a['amount_huf']);
        usort($excludedItems, fn (array $a, array $b) => $b['amount_huf'] <=> $a['amount_huf']);

        $excludedTotal = round(array_sum(array_column($excludedItems, 'amount_huf')), 2);

        return [
            'total_huf' => round($total, 2),
            'account_count' => $count,
            'items' => $items,
            'count_in_savings_total_huf' => round($countInSavingsTotal, 2),
            'excluded_items' => $excludedItems,
            'excluded_total_huf' => $excludedTotal,
            'exchange_rates_huf_per_unit' => $converter->rates(),
        ];
    }

    private function buildSavingItem(
        array $formatted,
        Saving $saving,
        float $nativeAmount,
        string $currency,
        float $amountHuf,
    ): array {
        return [
            'id' => 'saving-'.$saving->id,
            'label' => $this->itemLabel((string) ($formatted['institution'] ?? 'Megtakarítás'), $currency),
            'amount_huf' => $amountHuf,
            'amount_native' => round($nativeAmount, 2),
            'currency' => $currency,
            'kind' => ($saving->type ?? Saving::TYPE_ACCOUNT) === Saving::TYPE_GOAL ? 'goal' : 'account',
        ];
    }

    private function itemLabel(string $name, string $currency): string
    {
        $currency = strtoupper(trim($currency)) ?: 'HUF';

        return $name.' · '.$currency;
    }

    private function savingCurrency(array $formatted): string
    {
        return strtoupper(trim((string) ($formatted['currency'] ?? 'HUF'))) ?: 'HUF';
    }

    private function travelExclusionReason(array $formatted, string $separateOwner, bool $isInvestment): ?string
    {
        $owner = trim((string) ($formatted['owner'] ?? ''));
        if ($separateOwner !== '' && strcasecmp($owner, $separateOwner) === 0) {
            return 'separate_owner';
        }

        $label = $isInvestment
            ? (string) ($formatted['name'] ?? '')
            : (string) ($formatted['institution'] ?? '');

        if ($this->isStateTreasuryLabel($label)) {
            return 'state_treasury';
        }

        return null;
    }

    private function isStateTreasuryLabel(string $label): bool
    {
        $normalized = mb_strtolower(trim($label));

        return str_contains($normalized, 'államkincstár')
            || str_contains($normalized, 'allamkincstar');
    }

    private function savingBalanceNative(Saving $saving, array $formatted): float
    {
        $ledger = $formatted['ledger'] ?? [];
        if (is_array($ledger) && $ledger !== []) {
            if (($saving->type ?? Saving::TYPE_ACCOUNT) === Saving::TYPE_GOAL) {
                return (float) collect($ledger)->sum(fn ($entry) => abs((float) ($entry['amount'] ?? 0)));
            }

            return (float) collect($ledger)->sum(fn ($entry) => (float) ($entry['amount'] ?? 0));
        }

        return (float) ($formatted['current_amount'] ?? 0);
    }

    private function investmentValueHuf(Investment $investment, array $formatted): float
    {
        $current = $formatted['currentValue'] ?? null;
        if ($current !== null && (float) $current > 0) {
            return (float) $current;
        }

        $principal = (float) ($formatted['principalAmount'] ?? 0);
        $rate = (float) ($formatted['annualInterestRate'] ?? 0);
        $purchase = $investment->purchase_date ?? Carbon::today();
        $days = max(0, $purchase->diffInDays(Carbon::today()));
        $dailyRate = $rate / 100 / 365.25;

        return $principal + ($principal * $days * $dailyRate);
    }
}
