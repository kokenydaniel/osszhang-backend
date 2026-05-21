<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Utility;
use App\Models\UtilitySettlement;

class UtilityService
{
    public function __construct(
        private readonly UtilityBalanceService $balance,
        private readonly TransactionSensitiveData $sensitive,
        private readonly HouseholdCipherService $cipher,
        private readonly UtilitySettlementService $settlements,
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function formatSettlement(UtilitySettlement $s, Household $household, ?User $partner): array
    {
        $resolved = $this->crypto->settlementResolved($s, $household);
        $amount = (float) ($resolved['amount'] ?? 0);
        $direction = (string) ($resolved['direction'] ?? $s->direction);
        $partnerName = $partner?->first_name ?? 'Partner';

        $summary = match ($direction) {
            'partner_pays_household' => "{$partnerName} {$amount} Ft-ot fizetett neked (rezsi tartozás rendezve).",
            default => "Te fizettél {$partnerName} felé {$amount} Ft-ot (rezsi tartozás rendezve).",
        };

        return [
            'id' => $s->id,
            'year' => $s->year,
            'month' => $s->month,
            'amount' => $amount,
            'direction' => $direction,
            'settledAt' => $s->settled_at->format('Y-m-d'),
            'transactionId' => $s->transaction_id,
            'partnerName' => $partnerName,
            'summary' => $summary,
        ];
    }

    public function payloadForHousehold(int $householdId, Household $household): array
    {
        $partner = $household->utilitySplitPartner;

        $bills = Utility::where('household_id', $householdId)
            ->orderBy('due_date', 'desc')
            ->get()
            ->filter(fn (Utility $u) => ! UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto))
            ->map(fn (Utility $u) => $this->crypto->formatUtility($u, $household))
            ->values();

        $settlements = UtilitySettlement::where('household_id', $householdId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn (UtilitySettlement $s) => $this->formatSettlement($s, $household, $partner))
            ->values();

        return [
            'bills' => $bills,
            'settlements' => $settlements,
        ];
    }

    public function create(Household $household, array $validated): array
    {
        if (stripos($validated['type'], 'kiegyenlít') !== false) {
            throw new \InvalidArgumentException('Az elszámolást a „Tartozás rendezése” gombbal rögzítsd, ne új rezsi sorral.');
        }

        $u = new Utility([
            'household_id' => $household->id,
            'due_date' => $validated['dueDate'],
            'paid_date' => $validated['paidDate'] ?? null,
        ]);
        $this->crypto->persistUtility($u, $household, [
            'type' => $validated['type'],
            'total' => (float) $validated['total'],
            'paid_by' => $validated['paidBy'] ?? null,
            'split_rule' => $validated['splitRule'] ?? 'shared',
        ]);
        $u->save();

        return $this->crypto->formatUtility($u, $household);
    }

    public function update(Household $household, int|string $id, array $input): array
    {
        $u = Utility::where('household_id', $household->id)->findOrFail($id);

        if (UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto)) {
            throw new \InvalidArgumentException('Régi elszámolás-sor — töröld, majd használd a „Tartozás rendezése” gombot.');
        }

        $sensitive = $this->crypto->utilityResolved($u, $household);

        if (array_key_exists('type', $input)) {
            if (stripos($input['type'], 'kiegyenlít') !== false) {
                throw new \InvalidArgumentException('Az elszámolást a „Tartozás rendezése” gombbal rögzítsd.');
            }
            $sensitive['type'] = $input['type'];
        }
        if (array_key_exists('total', $input)) {
            $sensitive['total'] = (float) $input['total'];
        }
        if (array_key_exists('splitRule', $input)) {
            $sensitive['split_rule'] = $input['splitRule'];
        }
        if (array_key_exists('paidBy', $input)) {
            $sensitive['paid_by'] = $input['paidBy'];
        }
        if (array_key_exists('dueDate', $input)) {
            $u->due_date = $input['dueDate'];
        }
        if (array_key_exists('paidDate', $input)) {
            $u->paid_date = $input['paidDate'];
        }

        $this->crypto->persistUtility($u, $household, $sensitive);
        $u->save();

        return $this->crypto->formatUtility($u, $household);
    }

    public function delete(int $householdId, int|string $id): void
    {
        Utility::where('household_id', $householdId)->findOrFail($id)->delete();
    }

    public function settleMonth(Household $household, User $user, array $validated): array
    {
        if (! $household->utility_split_enabled) {
            throw new \InvalidArgumentException('A rezsi megosztás nincs bekapcsolva.');
        }

        $exists = UtilitySettlement::where('household_id', $household->id)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('Erre a hónapra már rögzítve van elszámolás.');
        }

        $monthStr = $validated['year'].'-'.str_pad((string) $validated['month'], 2, '0', STR_PAD_LEFT);

        $monthBills = Utility::where('household_id', $household->id)
            ->where('due_date', 'like', $monthStr.'%')
            ->get()
            ->filter(fn (Utility $u) => ! UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto));

        $totals = $this->balance->compute($monthBills, $user, true, $household, $this->crypto);
        $net = $totals['net_balance'];

        if (abs($net) < 0.01) {
            throw new \InvalidArgumentException('Nincs elszámolandó tartozás ebben a hónapban.');
        }

        $this->cipher->ensureCipherKey($household);

        $partner = $household->utilitySplitPartner;
        $partnerName = $partner?->first_name ?? 'Partner';
        $settledAt = now()->format('Y-m-d');
        $amount = abs($net);

        if ($net > 0) {
            $direction = 'partner_pays_household';
            $txType = 'income';
            $description = "Rezsi elszámolás – {$partnerName} befizette";
        } else {
            $direction = 'household_pays_partner';
            $txType = 'expense';
            $description = "Rezsi elszámolás – {$partnerName} felé kifizetve";
        }

        $transaction = new Transaction([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'type' => $txType,
            'due_date' => $settledAt,
            'paid_date' => $settledAt,
            'is_budget' => false,
            'is_reserve' => false,
        ]);

        $this->sensitive->persistSensitive($transaction, $household, [
            'description' => $description,
            'category' => 'Rezsi elszámolás',
            'amount' => $amount,
            'subItems' => [],
        ]);
        $transaction->save();

        if ($direction === 'partner_pays_household') {
            $this->crypto->adjustManualBalance($household, $amount);
        } else {
            $this->crypto->adjustManualBalance($household, -$amount);
        }

        Utility::where('household_id', $household->id)
            ->where('due_date', 'like', $monthStr.'%')
            ->get()
            ->each(function (Utility $u) use ($household) {
                if (UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto)) {
                    $u->delete();
                }
            });

        $settlement = new UtilitySettlement([
            'household_id' => $household->id,
            'year' => $validated['year'],
            'month' => $validated['month'],
            'settled_at' => $settledAt,
            'transaction_id' => $transaction->id,
        ]);
        $this->crypto->persistSettlement($settlement, $household, [
            'amount' => $amount,
            'direction' => $direction,
        ]);
        $settlement->save();

        $household->refresh();

        return [
            'message' => 'Elszámolás rögzítve.',
            'settlement' => $this->formatSettlement($settlement, $household, $partner),
            'manual_balance' => $this->crypto->resolvedManualBalance($household),
            ...$this->payloadForHousehold($household->id, $household->fresh()->load('utilitySplitPartner')),
        ];
    }

    public function unsettleMonth(Household $household, array $validated): array
    {
        $settlement = UtilitySettlement::where('household_id', $household->id)
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->first();

        if (! $settlement) {
            throw new \RuntimeException('Erre a hónapra nincs rögzített elszámolás.');
        }

        $this->settlements->revert($settlement, $household, true);
        $household->refresh();

        return [
            'message' => 'Elszámolás visszavonva.',
            'manual_balance' => $this->crypto->resolvedManualBalance($household),
            ...$this->payloadForHousehold($household->id, $household->fresh()->load('utilitySplitPartner')),
        ];
    }

    public function cloneMonth(Household $household, int $month, int $year): array
    {
        $householdId = $household->id;

        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear.'-'.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $toClone = Utility::where('household_id', $householdId)
            ->where('due_date', 'like', $prevMonthStr.'%')
            ->get()
            ->filter(fn (Utility $u) => ! UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto));

        $created = 0;
        foreach ($toClone as $bill) {
            $newDate = str_replace($prevMonthStr, $targetMonthStr, $bill->due_date);
            $source = $this->crypto->utilityResolved($bill, $household);

            $exists = Utility::where('household_id', $householdId)
                ->where('due_date', 'like', $targetMonthStr.'%')
                ->get()
                ->contains(fn (Utility $u) => ($this->crypto->utilityResolved($u, $household)['type'] ?? '') === ($source['type'] ?? ''));

            if ($exists) {
                continue;
            }

            $u = new Utility([
                'household_id' => $householdId,
                'due_date' => $newDate,
                'paid_date' => null,
            ]);
            $this->crypto->persistUtility($u, $household, [
                'type' => $source['type'],
                'total' => (float) ($source['total'] ?? 0),
                'paid_by' => null,
                'split_rule' => $source['split_rule'] ?? 'shared',
            ]);
            $u->save();
            $created++;
        }

        $household = $household->fresh()->load('utilitySplitPartner');

        return [
            'message' => "{$created} tétel átmásolva.",
            ...$this->payloadForHousehold($householdId, $household),
        ];
    }
}
