<?php

namespace App\Services;

use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Saving;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class SavingService
{
    public const GOAL_BUDGET_CATEGORY = 'Megtakarítási cél';
    public function __construct(
        private readonly EncryptedRecordService $crypto,
        private readonly WalletProvisioningService $wallets,
    ) {}

    public function listForUser(User $user, ?int $walletId = null): array
    {
        $household = $this->requireHousehold($user);

        return $this->accessibleSavingsQuery($user, $walletId)
            ->with(['ledger', 'wallet'])
            ->get()
            ->map(fn ($s) => $this->crypto->formatSaving($s, $household))
            ->all();
    }

    public function create(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $wallet = $this->resolveWalletForMutation(
            $user,
            isset($validated['walletId']) ? (int) $validated['walletId'] : null,
        );

        $type = $validated['type'] ?? Saving::TYPE_ACCOUNT;
        if (! in_array($type, [Saving::TYPE_ACCOUNT, Saving::TYPE_GOAL], true)) {
            $type = Saving::TYPE_ACCOUNT;
        }

        $isGoal = $type === Saving::TYPE_GOAL;

        $saving = new Saving([
            'household_id' => $household->id,
            'wallet_id' => $wallet->id,
            'travel_plan_id' => isset($validated['travelPlanId']) ? (int) $validated['travelPlanId'] : null,
            'type' => $type,
            'count_in_savings' => $validated['count_in_savings'] ?? true,
            'goal_amount' => $isGoal ? (float) ($validated['goal_amount'] ?? 0) : 0,
            'current_amount' => $isGoal ? (float) ($validated['current_amount'] ?? 0) : 0,
            'target_date' => $isGoal ? ($validated['target_date'] ?? null) : null,
        ]);
        $this->crypto->persistSaving($saving, $household, [
            'institution' => $validated['institution'],
            'currency' => $validated['currency'] ?? 'HUF',
            'owner' => $validated['owner'] ?? 'Közös',
        ]);
        $saving->save();

        $openingAmount = $isGoal ? (float) ($validated['current_amount'] ?? 0) : 0;
        if ($isGoal && $openingAmount > 0) {
            $entry = new LedgerEntry([
                'saving_id' => $saving->id,
                'date' => now()->toDateString(),
            ]);
            $this->crypto->persistLedger($entry, $household, [
                'amount' => $openingAmount,
                'reason' => 'Kezdő egyenleg',
            ]);
            $entry->save();
            $this->syncCurrentAmountFromLedger($saving, $household);
        }

        return $this->crypto->formatSaving($saving->load(['ledger', 'wallet']), $household);
    }

    public function addEntry(User $user, int|string $id, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $id);

        $entry = new LedgerEntry([
            'saving_id' => $saving->id,
            'date' => $validated['date'],
        ]);
        $this->crypto->persistLedger($entry, $household, [
            'amount' => $saving->type === Saving::TYPE_GOAL
                ? abs((float) $validated['amount'])
                : (float) $validated['amount'],
            'reason' => $validated['reason'],
        ]);
        $entry->save();

        $this->syncCurrentAmountFromLedger($saving, $household);

        return $this->crypto->formatSaving($saving->load(['ledger', 'wallet']), $household);
    }

    public function updateEntry(User $user, int|string $savingId, int|string $entryId, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $savingId);
        $entry = LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId);

        $sensitive = $this->crypto->ledgerResolved($entry, $household);
        if (array_key_exists('amount', $validated)) {
            $sensitive['amount'] = $saving->type === Saving::TYPE_GOAL
                ? abs((float) $validated['amount'])
                : (float) $validated['amount'];
        }
        if (array_key_exists('reason', $validated)) {
            $sensitive['reason'] = $validated['reason'];
        }
        if (array_key_exists('date', $validated)) {
            $entry->date = $validated['date'];
        }

        $this->crypto->persistLedger($entry, $household, $sensitive);
        $entry->save();

        $this->syncCurrentAmountFromLedger($saving, $household);

        return $this->crypto->formatSaving($saving->load(['ledger', 'wallet']), $household);
    }

    public function deleteEntry(User $user, int|string $savingId, int|string $entryId): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $savingId);
        LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId)->delete();

        $this->syncCurrentAmountFromLedger($saving, $household);

        return $this->crypto->formatSaving($saving->load(['ledger', 'wallet']), $household);
    }

    public function update(User $user, int|string $id, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $id);

        $sensitive = $this->crypto->savingResolved($saving, $household);
        if (array_key_exists('institution', $validated)) {
            $sensitive['institution'] = $validated['institution'];
        }
        if (array_key_exists('currency', $validated)) {
            $sensitive['currency'] = $validated['currency'];
        }
        if (array_key_exists('owner', $validated)) {
            $sensitive['owner'] = $validated['owner'];
        }
        if (array_key_exists('count_in_savings', $validated)) {
            $saving->count_in_savings = $validated['count_in_savings'];
        }
        if (array_key_exists('goal_amount', $validated)) {
            $saving->goal_amount = (float) $validated['goal_amount'];
        }
        if (array_key_exists('current_amount', $validated)) {
            $saving->current_amount = (float) $validated['current_amount'];
        }
        if (array_key_exists('target_date', $validated)) {
            $saving->target_date = $validated['target_date'];
        }
        if (array_key_exists('type', $validated)) {
            $saving->type = in_array($validated['type'], [Saving::TYPE_ACCOUNT, Saving::TYPE_GOAL], true)
                ? $validated['type']
                : $saving->type;
        }

        $this->crypto->persistSaving($saving, $household, $sensitive);
        $saving->save();

        return $this->crypto->formatSaving($saving->load(['ledger', 'wallet']), $household);
    }

    public function delete(User $user, int|string $id): void
    {
        $this->findAccessibleSaving($user, $id)->delete();
    }

    public static function goalVirtualId(int $savingId): string
    {
        return 'goal-'.$savingId;
    }

    public static function parseGoalVirtualId(int|string $id): ?int
    {
        if (! is_string($id) || ! str_starts_with($id, 'goal-')) {
            return null;
        }

        $savingId = (int) substr($id, 5);

        return $savingId > 0 ? $savingId : null;
    }

    public function buildGoalBudgetRowsForMonth(User $user, ?int $walletId, int $month, int $year): array
    {
        $household = $this->requireHousehold($user);
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();

        return $this->accessibleSavingsQuery($user, $walletId)
            ->where('type', Saving::TYPE_GOAL)
            ->whereNotNull('target_date')
            ->whereDate('target_date', '>=', $monthStart->toDateString())
            ->with('ledger')
            ->get()
            ->map(fn (Saving $saving) => $this->buildGoalBudgetRow($saving, $household, $month, $year))
            ->values()
            ->all();
    }

    public function buildGoalBudgetRow(Saving $saving, Household $household, int $month, int $year): array
    {
        $sensitive = $this->crypto->savingResolved($saving, $household);
        $institution = (string) ($sensitive['institution'] ?? '');
        $ledgerContext = $this->resolveGoalLedgerContext($saving, $household, $month, $year);
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        return [
            'id' => self::goalVirtualId($saving->id),
            'walletId' => $saving->wallet_id,
            'type' => 'expense',
            'description' => 'Cél: '.$institution,
            'category' => self::GOAL_BUDGET_CATEGORY,
            'amount' => $this->calculatePlannedFromSavedBefore(
                (float) $saving->goal_amount,
                $ledgerContext['savedBefore'],
                $month,
                $year,
                $saving->target_date,
            ),
            'dueDate' => $monthEnd->toDateString(),
            'paidDate' => null,
            'isBudget' => true,
            'isReserve' => false,
            'isSavingsGoal' => true,
            'savingGoalId' => $saving->id,
            'subItems' => $ledgerContext['monthEntries'],
        ];
    }

    private function resolveGoalLedgerContext(Saving $saving, Household $household, int $month, int $year): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $savedBefore = 0.0;
        $monthEntries = [];

        $saving->loadMissing('ledger');

        foreach ($saving->ledger as $entry) {
            $entryDate = Carbon::parse($entry->date);
            $amount = abs((float) ($this->crypto->ledgerResolved($entry, $household)['amount'] ?? 0));

            if ($entryDate->lt($monthStart)) {
                $savedBefore += $amount;
            } elseif ($entryDate->lte($monthEnd)) {
                $monthEntries[] = $this->crypto->formatLedgerEntry($entry, $household);
            }
        }

        return [
            'savedBefore' => $savedBefore,
            'monthEntries' => $monthEntries,
        ];
    }

    public function calculatePlannedFromSavedBefore(
        float $goalAmount,
        float $savedBeforeMonth,
        int $month,
        int $year,
        ?\DateTimeInterface $targetDate,
    ): float {
        if ($targetDate === null || $goalAmount <= 0) {
            return 0.0;
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $targetEnd = Carbon::parse($targetDate)->endOfMonth();
        if ($targetEnd->lt($monthStart)) {
            return 0.0;
        }

        $remaining = max(0.0, $goalAmount - $savedBeforeMonth);
        if ($remaining <= 0) {
            return 0.0;
        }

        $monthsLeft = max(
            1,
            ($targetEnd->year - $monthStart->year) * 12 + ($targetEnd->month - $monthStart->month) + 1,
        );

        return round($remaining / $monthsLeft, 2);
    }

    public function addGoalBudgetEntry(User $user, int $savingId, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $savingId);
        if ($saving->type !== Saving::TYPE_GOAL) {
            throw new AuthorizationException('Csak megtakarítási célhoz rögzíthető költségvetési tétel.');
        }

        $entry = new LedgerEntry([
            'saving_id' => $saving->id,
            'date' => $validated['date'],
        ]);
        $this->crypto->persistLedger($entry, $household, [
            'amount' => abs((float) $validated['amount']),
            'reason' => $validated['reason'],
        ]);
        $entry->save();

        $this->syncCurrentAmountFromLedger($saving, $household);

        $date = Carbon::parse($validated['date']);

        return $this->buildGoalBudgetRow(
            $saving->load(['ledger', 'wallet']),
            $household,
            (int) $date->month,
            (int) $date->year,
        );
    }

    public function deleteGoalBudgetEntry(User $user, int $savingId, int $entryId): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $savingId);
        $entry = LedgerEntry::where('saving_id', $saving->id)->findOrFail($entryId);
        $date = Carbon::parse($entry->date);
        $entry->delete();

        $this->syncCurrentAmountFromLedger($saving, $household);

        return $this->buildGoalBudgetRow(
            $saving->load(['ledger', 'wallet']),
            $household,
            (int) $date->month,
            (int) $date->year,
        );
    }

    public function upsertMonthlyActual(User $user, int $savingId, int $month, int $year, float $amount, ?string $reason = null): array
    {
        $household = $this->requireHousehold($user);
        $saving = $this->findAccessibleSaving($user, $savingId);
        if ($saving->type !== Saving::TYPE_GOAL) {
            throw new AuthorizationException('Csak megtakarítási célhoz állítható havi tény összeg.');
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $monthEntries = LedgerEntry::query()
            ->where('saving_id', $saving->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get();

        $normalizedAmount = abs($amount);
        $entryReason = $reason ?? 'Költségvetés – havi befizetés';

        if ($normalizedAmount <= 0) {
            $monthEntries->each->delete();
        } elseif ($monthEntries->isEmpty()) {
            $entry = new LedgerEntry([
                'saving_id' => $saving->id,
                'date' => $monthStart->toDateString(),
            ]);
            $this->crypto->persistLedger($entry, $household, [
                'amount' => $normalizedAmount,
                'reason' => $entryReason,
            ]);
            $entry->save();
        } else {
            $entry = $monthEntries->first();
            $sensitive = $this->crypto->ledgerResolved($entry, $household);
            $sensitive['amount'] = $normalizedAmount;
            $sensitive['reason'] = $entryReason;
            $entry->date = $monthStart->toDateString();
            $this->crypto->persistLedger($entry, $household, $sensitive);
            $entry->save();
            $monthEntries->slice(1)->each->delete();
        }

        $this->syncCurrentAmountFromLedger($saving, $household);

        return $this->buildGoalBudgetRow(
            $saving->load(['ledger', 'wallet']),
            $household,
            $month,
            $year,
        );
    }

    public function calculatePlannedMonthlyAmount(Saving $saving, Household $household, int $month, int $year): float
    {
        if ($saving->type !== Saving::TYPE_GOAL || $saving->target_date === null) {
            return 0.0;
        }

        $savedBeforeMonth = $this->resolveGoalLedgerContext($saving, $household, $month, $year)['savedBefore'];

        return $this->calculatePlannedFromSavedBefore(
            (float) $saving->goal_amount,
            $savedBeforeMonth,
            $month,
            $year,
            $saving->target_date,
        );
    }

    public function ledgerEntriesForMonth(Saving $saving, Household $household, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $saving->loadMissing('ledger');

        return $saving->ledger
            ->filter(fn (LedgerEntry $entry) => Carbon::parse($entry->date)->betweenIncluded($monthStart, $monthEnd))
            ->map(fn (LedgerEntry $entry) => $this->crypto->formatLedgerEntry($entry, $household))
            ->values()
            ->all();
    }

    public function ledgerSumBeforeMonth(Saving $saving, Household $household, int $year, int $month): float
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $saving->loadMissing('ledger');

        return (float) $saving->ledger
            ->filter(fn (LedgerEntry $entry) => Carbon::parse($entry->date)->lt($monthStart))
            ->sum(fn (LedgerEntry $entry) => abs((float) ($this->crypto->ledgerResolved($entry, $household)['amount'] ?? 0)));
    }

    public function ledgerSumForMonth(Saving $saving, Household $household, int $year, int $month): float
    {
        return (float) collect($this->ledgerEntriesForMonth($saving, $household, $year, $month))
            ->sum(fn (array $entry) => abs((float) ($entry['amount'] ?? 0)));
    }

    private function requireHousehold(User $user): Household
    {
        if ($user->household === null) {
            throw new AuthorizationException('Nincs háztartás a felhasználóhoz rendelve.');
        }

        return $user->household;
    }

    private function accessibleSavingsQuery(User $user, ?int $walletId = null): Builder
    {
        $query = Saving::query()->accessibleTo($user);

        if ($walletId !== null) {
            $query->where('wallet_id', $walletId);
        }

        return $query;
    }

    private function findAccessibleSaving(User $user, int|string $id): Saving
    {
        return $this->accessibleSavingsQuery($user)
            ->with(['ledger', 'wallet'])
            ->findOrFail($id);
    }

    private function syncCurrentAmountFromLedger(Saving $saving, Household $household): void
    {
        $saving->unsetRelation('ledger');
        $saving->load('ledger');

        if ($saving->type === Saving::TYPE_GOAL) {
            $total = (float) $saving->ledger->sum(
                fn (LedgerEntry $entry) => abs((float) ($this->crypto->ledgerResolved($entry, $household)['amount'] ?? 0)),
            );
            $saving->current_amount = $total;
            $saving->save();

            return;
        }

        if ($saving->ledger->isEmpty()) {
            return;
        }

        $total = (float) $saving->ledger->sum(
            fn (LedgerEntry $entry) => (float) ($this->crypto->ledgerResolved($entry, $household)['amount'] ?? 0),
        );
        $saving->current_amount = $total;
        $saving->save();
    }

    private function resolveWalletForMutation(User $user, ?int $walletId): Wallet
    {
        if ($walletId !== null) {
            return Wallet::query()
                ->accessibleTo($user)
                ->where('household_id', $user->household_id)
                ->findOrFail($walletId);
        }

        return $this->wallets->ensureSharedWallet($this->requireHousehold($user));
    }
}
