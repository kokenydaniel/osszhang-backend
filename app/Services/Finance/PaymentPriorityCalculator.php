<?php

namespace App\Services\Finance;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Utility;
use App\Models\Wallet;
use App\Services\EncryptedRecordService;
use App\Services\TransactionSensitiveData;
use Carbon\Carbon;

class PaymentPriorityCalculator
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
        private readonly TransactionSensitiveData $sensitive,
    ) {}

    public function buildQueue(Household $household, User $user, Wallet $wallet, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $today = Carbon::today();
        $items = [];

        $transactions = Transaction::query()
            ->where('household_id', $household->id)
            ->where('wallet_id', $wallet->id)
            ->whereNull('paid_date')
            ->where('type', 'expense')
            ->get();

        foreach ($transactions as $tx) {
            $sensitive = $this->sensitive->resolve($tx, $household);
            $amount = round((float) ($sensitive['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $due = Carbon::parse($tx->due_date);
            $monthPrefix = sprintf('%04d-%02d', $year, $month);
            $duePrefix = $due->format('Y-m');
            if ($duePrefix !== $monthPrefix && ! $due->lt($monthStart)) {
                continue;
            }
            $label = trim((string) ($sensitive['description'] ?? ''));
            if ($label === '' || $label === '—') {
                $label = trim((string) ($sensitive['category'] ?? 'Kiadás'));
            }
            $items[] = [
                'source' => 'budget',
                'id' => $tx->id,
                'label' => $label,
                'amount' => $amount,
                'currency' => $tx->currency ?? 'HUF',
                'due_date' => $due->toDateString(),
                'is_overdue' => $due->lt($today),
                'priority_score' => $this->score($due, $today, $amount, true),
            ];
        }

        $utilities = Utility::query()
            ->where('household_id', $household->id)
            ->whereNull('paid_date')
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->get();

        foreach ($utilities as $bill) {
            $amount = (float) ($bill->total ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $due = Carbon::parse($bill->due_date);
            $items[] = [
                'source' => 'utility',
                'id' => $bill->id,
                'label' => (string) $bill->type,
                'amount' => round($amount, 2),
                'currency' => 'HUF',
                'due_date' => $due->toDateString(),
                'is_overdue' => $due->lt($today),
                'priority_score' => $this->score($due, $today, $amount, false),
            ];
        }

        usort($items, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score'] ?: strcmp($a['due_date'], $b['due_date']));

        foreach ($items as $i => &$item) {
            $item['rank'] = $i + 1;
            unset($item['priority_score']);
        }

        return $items;
    }

    private function score(Carbon $due, Carbon $today, float $amount, bool $isBudget): int
    {
        $score = 0;
        if ($due->lt($today)) {
            $score += 10000 + min(999, $today->diffInDays($due));
        } else {
            $score += max(0, 5000 - $today->diffInDays($due) * 100);
        }
        $score += (int) min(999, $amount / 1000);
        if ($isBudget) {
            $score += 10;
        }

        return $score;
    }
}
