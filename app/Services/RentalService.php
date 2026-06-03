<?php

namespace App\Services;

use App\Models\Household;
use App\Models\RentalExpense;
use App\Models\RentalIncomeEntry;
use App\Models\RentalProperty;
use App\Models\User;
use App\Support\RentalSettings;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RentalService
{
    /** @return array<string, mixed> */
    public function index(User $user, ?int $year = null, ?int $month = null): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $settings = RentalSettings::resolve($household->rental_settings);

        $year = $year ?? (int) now()->format('Y');
        $month = $month ?? (int) now()->format('n');

        $properties = RentalProperty::query()
            ->where('household_id', $household->id)
            ->withCount('attachments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $propertyIds = $properties->pluck('id')->all();

        $entriesQuery = RentalIncomeEntry::query()
            ->whereIn('rental_property_id', $propertyIds)
            ->where('period_year', $year);

        if ($month !== null) {
            $entriesQuery->where('period_month', $month);
        }

        $entries = $entriesQuery
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->get()
            ->map(fn (RentalIncomeEntry $e) => $this->formatIncomeEntry($e))
            ->all();

        $propertiesPayload = $properties
            ->map(fn (RentalProperty $p) => $this->formatProperty($p))
            ->all();

        $expenses = RentalExpense::query()
            ->whereIn('rental_property_id', $propertyIds)
            ->where('expense_date', '>=', "{$year}-01-01")
            ->where('expense_date', '<=', "{$year}-12-31")
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (RentalExpense $e) => $this->formatExpense($e))
            ->all();

        $graceDays = (int) $settings['overdue_grace_days'];

        return [
            'properties' => $propertiesPayload,
            'incomeEntries' => $entries,
            'expenses' => $expenses,
            'summary' => $this->buildSummary($properties, collect($entries), collect($expenses), $year, $month),
            'upcomingContractEnds' => $this->buildUpcomingContractEnds(
                $propertiesPayload,
                (int) $settings['contract_reminder_days_before'],
            ),
            'overdueRents' => $this->buildOverdueRents(
                $properties,
                collect($entries),
                $year,
                $month,
                $graceDays,
            ),
            'selectedYear' => $year,
            'selectedMonth' => $month,
        ];
    }

    /** @param  array<string, mixed>  $validated */
    public function createProperty(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        $defaults = RentalSettings::resolve($household->rental_settings);
        $property = RentalProperty::create($this->propertyPayload($household->id, $validated, null, $defaults));

        return $this->formatProperty($property->fresh()->loadCount('attachments'));
    }

    /** @param  array<string, mixed>  $validated */
    public function updateProperty(User $user, int $id, array $validated): array
    {
        $property = $this->findPropertyForUser($user, $id);
        $property->update($this->propertyPayload($property->household_id, $validated, $property));

        return $this->formatProperty($property->fresh());
    }

    public function deleteProperty(User $user, int $id): array
    {
        $property = $this->findPropertyForUser($user, $id);
        $formatted = $this->formatProperty($property);
        $property->delete();

        return $formatted;
    }

    /** @param  array<string, mixed>  $validated */
    public function createIncome(User $user, array $validated): array
    {
        $property = $this->findPropertyForUser($user, (int) $this->value($validated, 'rentalPropertyId', 'rental_property_id'));

        $year = (int) $this->value($validated, 'periodYear', 'period_year');
        $month = (int) $this->value($validated, 'periodMonth', 'period_month');

        $this->assertUniquePeriod($property->id, $year, $month);

        $incomePayload = $this->incomeDefaultsFromProperty($property, $year, $month, $validated);

        $entry = RentalIncomeEntry::create($incomePayload);

        return $this->formatIncomeEntry($entry);
    }

    /** @param  array<string, mixed>  $validated */
    public function updateIncome(User $user, int $id, array $validated): array
    {
        $entry = $this->findIncomeForUser($user, $id);
        $payload = [];

        if (array_key_exists('amount', $validated)) {
            $payload['amount'] = (float) $validated['amount'];
        }
        if ($this->hasKey($validated, 'currency')) {
            $payload['currency'] = strtoupper((string) $validated['currency']);
        }
        if ($this->hasKey($validated, 'paidDate', 'paid_date')) {
            $payload['paid_date'] = $this->value($validated, 'paidDate', 'paid_date');
        }
        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'] ?: null;
        }
        if ($this->hasKey($validated, 'periodYear', 'period_year') || $this->hasKey($validated, 'periodMonth', 'period_month')) {
            $year = (int) $this->value($validated, 'periodYear', 'period_year', $entry->period_year);
            $month = (int) $this->value($validated, 'periodMonth', 'period_month', $entry->period_month);
            if ($year !== $entry->period_year || $month !== $entry->period_month) {
                $this->assertUniquePeriod($entry->rental_property_id, $year, $month, $entry->id);
            }
            $payload['period_year'] = $year;
            $payload['period_month'] = $month;
        }

        $entry->update($payload);

        return $this->formatIncomeEntry($entry->fresh());
    }

    public function deleteIncome(User $user, int $id): void
    {
        $this->findIncomeForUser($user, $id)->delete();
    }

    /** @param  array<string, mixed>  $validated */
    public function createExpense(User $user, array $validated): array
    {
        $property = $this->findPropertyForUser($user, (int) $this->value($validated, 'rentalPropertyId', 'rental_property_id'));

        $expense = RentalExpense::create([
            'rental_property_id' => $property->id,
            'expense_type' => (string) $this->value($validated, 'expenseType', 'expense_type', 'other'),
            'amount' => max(0, (float) $this->value($validated, 'amount', 'amount', 0)),
            'currency' => strtoupper((string) $this->value($validated, 'currency', 'currency', $property->currency)),
            'expense_date' => $this->value($validated, 'expenseDate', 'expense_date'),
            'note' => $this->value($validated, 'note', 'note'),
        ]);

        return $this->formatExpense($expense);
    }

    /** @param  array<string, mixed>  $validated */
    public function updateExpense(User $user, int $id, array $validated): array
    {
        $expense = $this->findExpenseForUser($user, $id);
        $payload = [];

        if ($this->hasKey($validated, 'expenseType', 'expense_type')) {
            $payload['expense_type'] = (string) $this->value($validated, 'expenseType', 'expense_type', $expense->expense_type);
        }
        if (array_key_exists('amount', $validated)) {
            $payload['amount'] = max(0, (float) $validated['amount']);
        }
        if ($this->hasKey($validated, 'currency')) {
            $payload['currency'] = strtoupper((string) $validated['currency']);
        }
        if ($this->hasKey($validated, 'expenseDate', 'expense_date')) {
            $payload['expense_date'] = $this->value($validated, 'expenseDate', 'expense_date');
        }
        if (array_key_exists('note', $validated)) {
            $payload['note'] = $validated['note'] ?: null;
        }

        $expense->update($payload);

        return $this->formatExpense($expense->fresh());
    }

    public function deleteExpense(User $user, int $id): void
    {
        $this->findExpenseForUser($user, $id)->delete();
    }

    /** @return list<array<string, mixed>> */
    public function exportRows(User $user, int $year): array
    {
        $data = $this->index($user, $year, null);
        $rows = [];
        $propertiesById = collect($data['properties'])->keyBy('id');

        foreach ($data['incomeEntries'] as $entry) {
            $property = $propertiesById->get($entry['rentalPropertyId']);
            $rows[] = [
                'Ingatlan' => $property['name'] ?? '',
                'Cím' => $property['address'] ?? '',
                'Bérlő' => $property['tenantName'] ?? '',
                'Év' => $entry['periodYear'],
                'Hónap' => $entry['periodMonth'],
                'Összeg' => $entry['amount'],
                'Pénznem' => $entry['currency'],
                'Befizetés napja' => $entry['paidDate'] ?? '',
                'Megjegyzés' => $entry['note'] ?? '',
                'Havi bérleti díj' => $property['monthlyRent'] ?? 0,
                'Közös költség' => $property['monthlyCommonCost'] ?? 0,
                'Szerződés vége' => $property['contractEndsAt'] ?? '',
            ];
        }

        return $rows;
    }

    /** @param  Collection<int, RentalProperty>  $properties */
    /** @param  Collection<int, array<string, mixed>>  $entries */
    /** @return array<string, mixed> */
    /** @param  Collection<int, array<string, mixed>>  $expenses */
    private function buildSummary(
        Collection $properties,
        Collection $entries,
        Collection $expenses,
        int $year,
        ?int $month,
    ): array {
        $active = $properties->where('is_active', true);

        $filteredEntries = $month !== null
            ? $entries->filter(
                fn (array $e) => (int) $e['periodYear'] === $year && (int) $e['periodMonth'] === $month,
            )
            : $entries->filter(fn (array $e) => (int) $e['periodYear'] === $year);

        $filteredExpenses = $month !== null
            ? $expenses->filter(fn (array $e) => $this->expenseInMonth($e, $year, $month))
            : $expenses->filter(fn (array $e) => str_starts_with((string) ($e['expenseDate'] ?? ''), (string) $year));

        $expectedRent = 0.0;
        $expectedCommon = 0.0;
        $propertyCount = $month !== null ? $active->count() : 0;

        if ($month !== null) {
            foreach ($active as $property) {
                $expectedRent += (float) $property->monthly_rent;
                $expectedCommon += (float) $property->monthly_common_cost;
            }
        }

        $expectedGross = $expectedRent + $expectedCommon;
        $ownerExpenses = $filteredExpenses->sum(fn (array $e) => (float) $e['amount']);

        $received = 0.0;
        $paidCount = 0;
        foreach ($filteredEntries as $entry) {
            if (! empty($entry['paidDate'])) {
                $received += (float) $entry['amount'];
                $paidCount++;
            }
        }

        $expectedFromTenant = $expectedGross;
        $recordedCount = $filteredEntries->count();
        $unpaidCount = 0;
        if ($month !== null) {
            foreach ($active as $property) {
                $entry = $filteredEntries->first(
                    fn (array $e) => (int) $e['rentalPropertyId'] === $property->id
                        && (int) $e['periodYear'] === $year
                        && (int) $e['periodMonth'] === $month,
                );
                if ($this->tenantOutstandingForProperty($property, $entry) >= 0.005) {
                    $unpaidCount++;
                }
            }
        }

        return [
            'expectedRent' => round($expectedRent, 2),
            'expectedCommonCost' => round($expectedCommon, 2),
            'expectedGross' => round($expectedGross, 2),
            'commonCostTotal' => round($expectedCommon, 2),
            'expectedNet' => round($expectedFromTenant, 2),
            'ownerExpenses' => round($ownerExpenses, 2),
            'received' => round($received, 2),
            'outstanding' => round(max(0, $expectedFromTenant - $received), 2),
            'propertyCount' => $propertyCount,
            'paidCount' => $paidCount,
            'recordedCount' => $recordedCount,
            'unpaidCount' => $unpaidCount,
        ];
    }

    /** @param  Collection<int, RentalProperty>  $properties */
    /** @param  Collection<int, array<string, mixed>>  $entries */
    /** @return list<array<string, mixed>> */
    private function buildOverdueRents(
        Collection $properties,
        Collection $entries,
        int $year,
        int $month,
        int $graceDays,
    ): array {
        $today = Carbon::today();
        $items = [];

        foreach ($properties->where('is_active', true) as $property) {
            $entry = $entries->first(
                fn (array $e) => (int) $e['rentalPropertyId'] === $property->id
                    && (int) $e['periodYear'] === $year
                    && (int) $e['periodMonth'] === $month,
            );

            $outstanding = $this->tenantOutstandingForProperty($property, $entry);
            if ($outstanding < 0.005) {
                continue;
            }

            $dueDate = $entry['dueDate'] ?? $this->periodDueDate($property, $year, $month);
            $due = Carbon::parse($dueDate)->addDays($graceDays);
            if ($today->lte($due)) {
                continue;
            }

            $items[] = [
                'propertyId' => $property->id,
                'incomeEntryId' => $entry['id'] ?? null,
                'name' => $property->name,
                'tenantName' => $property->tenant_name,
                'dueDate' => $dueDate,
                'amount' => $outstanding,
                'currency' => $property->currency,
                'daysOverdue' => (int) $due->diffInDays($today, false),
                'isPartial' => $entry !== null && ! empty($entry['paidDate']),
            ];
        }

        usort($items, fn ($a, $b) => strcmp((string) $a['dueDate'], (string) $b['dueDate']));

        return $items;
    }

    private function periodDueDate(RentalProperty $property, int $year, int $month): string
    {
        $day = min(max((int) ($property->due_day ?? 5), 1), 28);

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function expectedTenantAmountForProperty(RentalProperty $property): float
    {
        return round((float) $property->monthly_rent + (float) $property->monthly_common_cost, 2);
    }

    /** @param  array<string, mixed>|null  $entry */
    private function receivedTenantAmountForEntry(?array $entry): float
    {
        if ($entry === null || empty($entry['paidDate'])) {
            return 0.0;
        }

        return (float) $entry['amount'];
    }

    /** @param  array<string, mixed>|null  $entry */
    private function tenantOutstandingForProperty(RentalProperty $property, ?array $entry): float
    {
        $expected = $this->expectedTenantAmountForProperty($property);

        return round(max(0, $expected - $this->receivedTenantAmountForEntry($entry)), 2);
    }

    /** @param  array<string, mixed>  $validated */
    /** @return array<string, mixed> */
    private function incomeDefaultsFromProperty(RentalProperty $property, int $year, int $month, array $validated): array
    {
        $rent = (float) $this->value($validated, 'rentAmount', 'rent_amount', $property->monthly_rent);
        $common = (float) $this->value($validated, 'commonCostAmount', 'common_cost_amount', $property->monthly_common_cost);
        $amount = $this->hasKey($validated, 'amount')
            ? (float) $validated['amount']
            : $rent + $common;

        return [
            'rental_property_id' => $property->id,
            'amount' => $amount,
            'rent_amount' => $rent,
            'common_cost_amount' => $common,
            'currency' => strtoupper((string) $this->value($validated, 'currency', 'currency', $property->currency)),
            'period_year' => $year,
            'period_month' => $month,
            'due_date' => $this->value($validated, 'dueDate', 'due_date', $this->periodDueDate($property, $year, $month)),
            'paid_date' => $this->value($validated, 'paidDate', 'paid_date'),
            'note' => $this->value($validated, 'note', 'note'),
        ];
    }

    /** @param  array<string, mixed>  $expense */
    private function expenseInMonth(array $expense, int $year, int $month): bool
    {
        $date = (string) ($expense['expenseDate'] ?? '');
        $prefix = sprintf('%04d-%02d', $year, $month);

        return str_starts_with($date, $prefix);
    }

    /** @param  list<array<string, mixed>>  $properties */
    /** @return list<array<string, mixed>> */
    private function buildUpcomingContractEnds(array $properties, int $reminderDays): array
    {
        $today = Carbon::today();
        $limit = $today->copy()->addDays($reminderDays);
        $items = [];

        foreach ($properties as $property) {
            if (! $property['isActive'] || empty($property['contractEndsAt'])) {
                continue;
            }
            $ends = Carbon::parse($property['contractEndsAt']);
            if ($ends->gt($limit)) {
                continue;
            }
            $items[] = [
                'propertyId' => $property['id'],
                'name' => $property['name'],
                'contractEndsAt' => $property['contractEndsAt'],
                'tenantName' => $property['tenantName'],
                'overdue' => $ends->lt($today),
                'daysLeft' => (int) $today->diffInDays($ends, false),
            ];
        }

        usort($items, fn ($a, $b) => strcmp((string) $a['contractEndsAt'], (string) $b['contractEndsAt']));

        return $items;
    }

    private function assertUniquePeriod(int $propertyId, int $year, int $month, ?int $exceptId = null): void
    {
        $exists = RentalIncomeEntry::query()
            ->where('rental_property_id', $propertyId)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->when($exceptId !== null, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'period_month' => ['Erre az ingatlanra és hónapra már van bevétel rögzítve.'],
            ]);
        }
    }

    /** @param  array<string, mixed>  $validated */
    /** @param  array<string, mixed>|null  $defaults */
    private function propertyPayload(
        int $householdId,
        array $validated,
        ?RentalProperty $existing = null,
        ?array $defaults = null,
    ): array {
        $payload = ['household_id' => $householdId];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }
        if (array_key_exists('address', $validated)) {
            $payload['address'] = $validated['address'] ?: null;
        }
        if ($this->hasKey($validated, 'monthlyRent', 'monthly_rent')) {
            $payload['monthly_rent'] = max(0, (float) $this->value($validated, 'monthlyRent', 'monthly_rent', 0));
        }
        if ($this->hasKey($validated, 'monthlyCommonCost', 'monthly_common_cost')) {
            $payload['monthly_common_cost'] = max(0, (float) $this->value($validated, 'monthlyCommonCost', 'monthly_common_cost', 0));
        }
        if ($this->hasKey($validated, 'currency')) {
            $payload['currency'] = strtoupper((string) $validated['currency']);
        }
        if ($this->hasKey($validated, 'tenantName', 'tenant_name')) {
            $payload['tenant_name'] = $this->value($validated, 'tenantName', 'tenant_name') ?: null;
        }
        if ($this->hasKey($validated, 'contractEndsAt', 'contract_ends_at')) {
            $payload['contract_ends_at'] = $this->value($validated, 'contractEndsAt', 'contract_ends_at') ?: null;
        }
        if ($this->hasKey($validated, 'dueDay', 'due_day')) {
            $payload['due_day'] = min(28, max(1, (int) $this->value($validated, 'dueDay', 'due_day', 5)));
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'] ?: null;
        }
        if ($this->hasKey($validated, 'agreementNotes', 'agreement_notes')) {
            $payload['agreement_notes'] = $this->value($validated, 'agreementNotes', 'agreement_notes') ?: null;
        }
        if ($this->hasKey($validated, 'isActive', 'is_active')) {
            $payload['is_active'] = (bool) $this->value($validated, 'isActive', 'is_active', true);
        }
        if ($this->hasKey($validated, 'budgetSyncEnabled', 'budget_sync_enabled')) {
            $payload['budget_sync_enabled'] = (bool) $this->value($validated, 'budgetSyncEnabled', 'budget_sync_enabled', false);
        }

        if ($existing === null) {
            if (! isset($payload['is_active'])) {
                $payload['is_active'] = true;
            }
            if (! isset($payload['due_day'])) {
                $payload['due_day'] = 5;
            }
            if (! isset($payload['budget_sync_enabled']) && $defaults !== null) {
                $payload['budget_sync_enabled'] = (bool) ($defaults['budget_sync_default'] ?? false);
            }
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function formatProperty(RentalProperty $property): array
    {
        return [
            'id' => $property->id,
            'name' => $property->name,
            'address' => $property->address,
            'monthlyRent' => (float) $property->monthly_rent,
            'monthlyCommonCost' => (float) ($property->monthly_common_cost ?? 0),
            'currency' => $property->currency,
            'tenantName' => $property->tenant_name,
            'dueDay' => (int) ($property->due_day ?? 5),
            'notes' => $property->notes,
            'agreementNotes' => $property->agreement_notes,
            'contractEndsAt' => $property->contract_ends_at?->format('Y-m-d'),
            'isActive' => (bool) $property->is_active,
            'budgetSyncEnabled' => (bool) $property->budget_sync_enabled,
            'attachmentCount' => (int) ($property->attachments_count ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function formatIncomeEntry(RentalIncomeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'rentalPropertyId' => $entry->rental_property_id,
            'amount' => (float) $entry->amount,
            'rentAmount' => (float) ($entry->rent_amount ?? $entry->amount),
            'commonCostAmount' => (float) ($entry->common_cost_amount ?? 0),
            'currency' => $entry->currency,
            'periodYear' => (int) $entry->period_year,
            'periodMonth' => (int) $entry->period_month,
            'dueDate' => $entry->due_date?->format('Y-m-d'),
            'paidDate' => $entry->paid_date?->format('Y-m-d'),
            'note' => $entry->note,
        ];
    }

    /** @return array<string, mixed> */
    private function formatExpense(RentalExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'rentalPropertyId' => $expense->rental_property_id,
            'expenseType' => $expense->expense_type,
            'amount' => (float) $expense->amount,
            'currency' => $expense->currency,
            'expenseDate' => $expense->expense_date->format('Y-m-d'),
            'note' => $expense->note,
        ];
    }

    private function findExpenseForUser(User $user, int $id): RentalExpense
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return RentalExpense::query()
            ->whereHas('property', fn ($q) => $q->where('household_id', $household->id))
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findPropertyForUser(User $user, int $id): RentalProperty
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return RentalProperty::query()
            ->where('household_id', $household->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function findIncomeForUser(User $user, int $id): RentalIncomeEntry
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return RentalIncomeEntry::query()
            ->whereHas('property', fn ($q) => $q->where('household_id', $household->id))
            ->whereKey($id)
            ->firstOrFail();
    }

    private function requireHousehold(User $user): Household
    {
        $household = $user->household;
        if (! $household) {
            throw new AuthorizationException('Nincs háztartás.');
        }

        return $household;
    }

    private function assertModuleEnabled(Household $household): void
    {
        if (! $household->rental_enabled) {
            throw new AuthorizationException('A bérbeadás modul nincs bekapcsolva.');
        }
    }

    /** @param  array<string, mixed>  $data */
    private function hasKey(array $data, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $data */
    private function value(array $data, string $camel, string $snake, mixed $default = null): mixed
    {
        if (array_key_exists($camel, $data)) {
            return $data[$camel];
        }
        if (array_key_exists($snake, $data)) {
            return $data[$snake];
        }

        return $default;
    }
}
