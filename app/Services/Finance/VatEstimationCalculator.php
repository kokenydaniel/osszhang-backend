<?php

namespace App\Services\Finance;

use App\Models\BusinessOrder;
use App\Models\Household;
use App\Services\EncryptedRecordService;
use App\Support\BusinessTaxRevenue;

class VatEstimationCalculator
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    /**
     * Bevétel és ÁFA bontás rögzített rendelésekből — adózási beállítások szerint szűrve.
     *
     * @return array<string, mixed>
     */
    public function calculate(Household $household, int $year, int $month): array
    {
        $biz = $household->resolvedBusinessSettings();
        $vatPercent = max(0.0, min(100.0, (float) ($biz['default_vat_percent'] ?? 27)));
        $priceMode = ($biz['price_input_mode'] ?? 'gross') === 'net' ? 'net' : 'gross';
        $taxRegime = (string) ($biz['tax_regime'] ?? 'aam');
        if (! in_array($taxRegime, ['aam', 'vat', 'kata'], true)) {
            $taxRegime = 'aam';
        }
        $incomeMethod = (string) ($biz['income_tax_method'] ?? 'cost_ratio');
        if (! in_array($incomeMethod, ['cost_ratio', 'actual', 'kata_flat'], true)) {
            $incomeMethod = 'cost_ratio';
        }
        $costRatioPercent = max(0.0, min(100.0, (float) ($biz['cost_ratio_percent'] ?? 40)));
        $revenueBasis = (string) ($biz['revenue_basis'] ?? 'documented_only');
        if (! in_array($revenueBasis, ['documented_only', 'all_orders'], true)) {
            $revenueBasis = 'documented_only';
        }

        if ($taxRegime === 'aam' || $taxRegime === 'kata') {
            $vatPercent = 0.0;
        }

        $orders = BusinessOrder::query()
            ->where('household_id', $household->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date')
            ->get();

        $lines = [];
        $grossTotal = 0.0;
        $skippedCount = 0;

        foreach ($orders as $order) {
            if (! BusinessTaxRevenue::countsAsRevenue($order, $household, $this->crypto, $revenueBasis)) {
                $skippedCount++;

                continue;
            }

            $resolved = $this->crypto->businessOrderResolved($order, $household);
            $rawAmount = (float) ($resolved['amount'] ?? 0);
            $amount = BusinessTaxRevenue::toNetAmount($rawAmount, $biz);
            if ($amount <= 0) {
                continue;
            }
            $grossTotal += $amount;
            $lines[] = [
                'order_id' => $order->id,
                'date' => $order->date,
                'customer_name' => (string) ($resolved['customer_name'] ?? ''),
                'recorded_amount' => $amount,
                'currency' => 'HUF',
                'has_invoice' => (bool) $order->has_invoice,
                'invoice_id' => $resolved['invoice_id'] ?? null,
            ];
        }

        if ($priceMode === 'net') {
            $netTotal = round($grossTotal, 2);
            $vatAmount = round($netTotal * $vatPercent / 100, 2);
            $grossComputed = round($netTotal + $vatAmount, 2);
        } else {
            $grossComputed = round($grossTotal, 2);
            $netTotal = $vatPercent > 0
                ? round($grossComputed / (1 + $vatPercent / 100), 2)
                : $grossComputed;
            $vatAmount = round($grossComputed - $netTotal, 2);
        }

        $estimatedTaxableIncome = null;
        $estimatedCostShare = null;
        if ($taxRegime === 'aam' && $incomeMethod === 'cost_ratio') {
            $estimatedCostShare = round($netTotal * $costRatioPercent / 100, 2);
            $estimatedTaxableIncome = round($netTotal - $estimatedCostShare, 2);
        } elseif ($taxRegime === 'aam' && $incomeMethod === 'actual') {
            $estimatedTaxableIncome = $netTotal;
        }

        $note = $revenueBasis === 'documented_only'
            ? 'Csak számlás vagy nyugtás (jelölt) rendelések számítanak bevételnek. A SZJA/KIVA összeg nem automatikus adóbevallás.'
            : 'Minden pozitív összegű rendelés bevételnek számít. Ellenőrizd könyvelővel.';

        return [
            'year' => $year,
            'month' => $month,
            'order_count' => count($lines),
            'skipped_order_count' => $skippedCount,
            'lines' => $lines,
            'price_input_mode' => $priceMode,
            'vat_percent' => $vatPercent,
            'net_total' => $netTotal,
            'vat_amount' => $vatAmount,
            'gross_total' => $grossComputed,
            'tax_regime' => $taxRegime,
            'income_tax_method' => $incomeMethod,
            'cost_ratio_percent' => $costRatioPercent,
            'revenue_basis' => $revenueBasis,
            'estimated_taxable_income' => $estimatedTaxableIncome,
            'estimated_cost_share' => $estimatedCostShare,
            'calculation_mode' => 'deterministic',
            'note' => $note,
        ];
    }
}
