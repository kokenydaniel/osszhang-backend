<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\Utility;
use App\Models\UtilitySettlement;
use App\Models\User;
use App\Services\EncryptedRecordService;
use App\Services\HouseholdCipherService;
use App\Services\TransactionSensitiveData;
use App\Services\UtilityBalanceService;
use App\Services\UtilitySettlementService;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    public function __construct(
        private readonly UtilityBalanceService $balance,
        private readonly TransactionSensitiveData $sensitive,
        private readonly HouseholdCipherService $cipher,
        private readonly UtilitySettlementService $settlements,
        private readonly EncryptedRecordService $crypto,
    ) {}

    private function formatSettlement(UtilitySettlement $s, Household $household, ?User $partner): array
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

    private function payloadForHousehold(int $householdId, $household): array
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

    public function index(Request $request)
    {
        $household = $request->user()->household()->with('utilitySplitPartner')->first();

        return response()->json($this->payloadForHousehold($household->id, $household));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'type' => 'required|string|max:100',
            'total' => 'required|numeric|min:0',
            'dueDate' => 'required|date',
            'splitRule' => 'nullable|in:shared,dani-private,ildi-private',
            'paidBy' => 'nullable|in:Mi,Ildi',
            'paidDate' => 'nullable|date',
        ]);

        if (stripos($v['type'], 'kiegyenlít') !== false) {
            return response()->json(['message' => 'Az elszámolást a „Tartozás rendezése” gombbal rögzítsd, ne új rezsi sorral.'], 422);
        }

        $household = $request->user()->household;
        $u = new Utility([
            'household_id' => $household->id,
            'due_date' => $v['dueDate'],
            'paid_date' => $v['paidDate'] ?? null,
        ]);
        $this->crypto->persistUtility($u, $household, [
            'type' => $v['type'],
            'total' => (float) $v['total'],
            'paid_by' => $v['paidBy'] ?? null,
            'split_rule' => $v['splitRule'] ?? 'shared',
        ]);
        $u->save();

        return response()->json($this->crypto->formatUtility($u, $household), 201);
    }

    public function update(Request $request, $id)
    {
        $household = $request->user()->household;
        $u = Utility::where('household_id', $household->id)->findOrFail($id);

        if (UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto)) {
            return response()->json(['message' => 'Régi elszámolás-sor — töröld, majd használd a „Tartozás rendezése” gombot.'], 422);
        }

        $sensitive = $this->crypto->utilityResolved($u, $household);

        if ($request->has('type')) {
            if (stripos($request->type, 'kiegyenlít') !== false) {
                return response()->json(['message' => 'Az elszámolást a „Tartozás rendezése” gombbal rögzítsd.'], 422);
            }
            $sensitive['type'] = $request->type;
        }
        if ($request->has('total')) {
            $sensitive['total'] = (float) $request->total;
        }
        if ($request->has('splitRule')) {
            $sensitive['split_rule'] = $request->splitRule;
        }
        if ($request->has('paidBy')) {
            $sensitive['paid_by'] = $request->paidBy;
        }
        if ($request->has('dueDate')) {
            $u->due_date = $request->dueDate;
        }
        if ($request->has('paidDate')) {
            $u->paid_date = $request->paidDate;
        }

        $this->crypto->persistUtility($u, $household, $sensitive);
        $u->save();

        return response()->json($this->crypto->formatUtility($u, $household));
    }

    public function destroy(Request $request, $id)
    {
        Utility::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function settleMonth(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Csak adminisztrátor rögzíthet elszámolást.'], 403);
        }

        $v = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $request->user()->household()->with('utilitySplitPartner')->first();
        if (! $household->utility_split_enabled) {
            return response()->json(['message' => 'A rezsi megosztás nincs bekapcsolva.'], 422);
        }

        $exists = UtilitySettlement::where('household_id', $household->id)
            ->where('year', $v['year'])
            ->where('month', $v['month'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Erre a hónapra már rögzítve van elszámolás.'], 422);
        }

        $monthStr = $v['year'].'-'.str_pad((string) $v['month'], 2, '0', STR_PAD_LEFT);

        $monthBills = Utility::where('household_id', $household->id)
            ->where('due_date', 'like', $monthStr.'%')
            ->get()
            ->filter(fn (Utility $u) => ! UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto));

        $totals = $this->balance->compute($monthBills, $request->user(), true, $household, $this->crypto);
        $net = $totals['net_balance'];

        if (abs($net) < 0.01) {
            return response()->json(['message' => 'Nincs elszámolandó tartozás ebben a hónapban.'], 422);
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
            'user_id' => $request->user()->id,
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
            'year' => $v['year'],
            'month' => $v['month'],
            'settled_at' => $settledAt,
            'transaction_id' => $transaction->id,
        ]);
        $this->crypto->persistSettlement($settlement, $household, [
            'amount' => $amount,
            'direction' => $direction,
        ]);
        $settlement->save();

        $household->refresh();

        return response()->json([
            'message' => 'Elszámolás rögzítve.',
            'settlement' => $this->formatSettlement($settlement, $household, $partner),
            'manual_balance' => $this->crypto->resolvedManualBalance($household),
            ...$this->payloadForHousehold($household->id, $household->fresh()->load('utilitySplitPartner')),
        ]);
    }

    public function unsettleMonth(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Csak adminisztrátor vonhatja vissza az elszámolást.'], 403);
        }

        $v = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $request->user()->household;

        $settlement = UtilitySettlement::where('household_id', $household->id)
            ->where('year', $v['year'])
            ->where('month', $v['month'])
            ->first();

        if (! $settlement) {
            return response()->json(['message' => 'Erre a hónapra nincs rögzített elszámolás.'], 404);
        }

        $this->settlements->revert($settlement, $household, true);
        $household->refresh();

        return response()->json([
            'message' => 'Elszámolás visszavonva.',
            'manual_balance' => $this->crypto->resolvedManualBalance($household),
            ...$this->payloadForHousehold($household->id, $household->fresh()->load('utilitySplitPartner')),
        ]);
    }

    public function cloneMonth(Request $request)
    {
        $household = $request->user()->household;
        $householdId = $household->id;
        $targetMonth = (int) $request->month;
        $targetYear = (int) $request->year;

        $prevMonth = $targetMonth - 1;
        $prevYear = $targetYear;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear.'-'.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $targetYear.'-'.str_pad((string) $targetMonth, 2, '0', STR_PAD_LEFT);

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

        return response()->json([
            'message' => "{$created} tétel átmásolva.",
            ...$this->payloadForHousehold($householdId, $household),
        ]);
    }
}
