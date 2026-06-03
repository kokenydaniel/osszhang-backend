<?php

namespace App\Services;

use App\Models\Household;
use App\Models\InsurancePolicy;
use App\Models\User;
use App\Support\InsuranceSettings;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class InsurancePolicyService
{
    private const PAYMENT_FREQUENCIES = ['monthly', 'quarterly', 'semiannual', 'annual'];

    private const POLICY_KINDS = ['general', 'life_investment'];

    public function __construct(
        private readonly AttachmentService $attachments,
    ) {}

    /** @return array{policies: list<array<string, mixed>>, budgetPolicies: list<array<string, mixed>>, upcoming: list<array<string, mixed>>} */
    public function listForUser(User $user): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);
        $settings = InsuranceSettings::resolve($household->insurance_settings);

        $baseQuery = InsurancePolicy::query()
            ->where('household_id', $household->id)
            ->withCount('attachments')
            ->orderByDesc('is_active')
            ->orderBy('name');

        $policies = (clone $baseQuery)
            ->get()
            ->map(fn (InsurancePolicy $p) => $this->formatPolicy($p))
            ->all();

        $budgetPolicies = (clone $baseQuery)
            ->withTrashed()
            ->get()
            ->map(fn (InsurancePolicy $p) => $this->formatPolicy($p))
            ->all();

        return [
            'policies' => $policies,
            'budgetPolicies' => $budgetPolicies,
            'upcoming' => $this->buildUpcomingReminders($policies, (int) $settings['reminder_days_before']),
        ];
    }

    /** @return array<string, mixed> */
    public function create(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        $policy = InsurancePolicy::create($this->payloadFromValidated($household->id, $validated));

        return $this->formatPolicy($policy->fresh()->loadCount('attachments'));
    }

    /** @return array<string, mixed> */
    public function update(User $user, int $id, array $validated): array
    {
        $policy = $this->findForUser($user, $id);

        $policy->update($this->payloadFromValidated($policy->household_id, $validated, $policy));

        return $this->formatPolicy($policy->fresh()->loadCount('attachments'));
    }

    /** @return array<string, mixed> */
    public function delete(User $user, int $id): array
    {
        $policy = $this->findForUser($user, $id);
        $policy->delete();

        return $this->formatPolicy($policy->fresh()->loadCount('attachments'));
    }

    public function findForUser(User $user, int $id): InsurancePolicy
    {
        $household = $this->requireHousehold($user);
        $this->assertModuleEnabled($household);

        return InsurancePolicy::query()
            ->where('household_id', $household->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /** @param  array<string, mixed>  $validated */
    private function payloadFromValidated(int $householdId, array $validated, ?InsurancePolicy $existing = null): array
    {
        $payload = ['household_id' => $householdId];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }
        if (array_key_exists('insurer', $validated)) {
            $payload['insurer'] = $validated['insurer'] ?: null;
        }
        if ($this->hasValidatedKey($validated, 'policyKind', 'policy_kind')) {
            $kind = (string) $this->validatedValue($validated, 'policyKind', 'policy_kind', 'general');
            $payload['policy_kind'] = in_array($kind, self::POLICY_KINDS, true) ? $kind : 'general';
        }
        if ($this->hasValidatedKey($validated, 'fundValue', 'fund_value')) {
            $payload['fund_value'] = $this->validatedValue($validated, 'fundValue', 'fund_value');
        }
        if ($this->hasValidatedKey($validated, 'premiumFree', 'premium_free')) {
            $payload['premium_free'] = (bool) $this->validatedValue($validated, 'premiumFree', 'premium_free', false);
        }
        if ($this->hasValidatedKey($validated, 'paymentFrequency', 'payment_frequency')) {
            $freq = (string) $this->validatedValue($validated, 'paymentFrequency', 'payment_frequency', 'annual');
            $payload['payment_frequency'] = in_array($freq, self::PAYMENT_FREQUENCIES, true) ? $freq : 'annual';
        }
        if ($this->hasValidatedKey($validated, 'paymentAmount', 'payment_amount')) {
            $payload['payment_amount'] = (float) $this->validatedValue($validated, 'paymentAmount', 'payment_amount', 0);
        }
        if ($this->hasValidatedKey($validated, 'annualPremium', 'annual_premium')) {
            $payload['annual_premium'] = (float) $this->validatedValue($validated, 'annualPremium', 'annual_premium', 0);
        }
        if (array_key_exists('currency', $validated)) {
            $payload['currency'] = strtoupper(trim((string) $validated['currency'])) ?: 'HUF';
        }
        if ($this->hasValidatedKey($validated, 'renewalDate', 'renewal_date')) {
            $payload['renewal_date'] = $this->validatedValue($validated, 'renewalDate', 'renewal_date');
        }
        if ($this->hasValidatedKey($validated, 'coveredUntil', 'covered_until')) {
            $payload['covered_until'] = $this->validatedValue($validated, 'coveredUntil', 'covered_until');
        }
        if (array_key_exists('notes', $validated)) {
            $payload['notes'] = $validated['notes'] ?: null;
        }
        if ($this->hasValidatedKey($validated, 'isActive', 'is_active')) {
            $payload['is_active'] = (bool) $this->validatedValue($validated, 'isActive', 'is_active', true);
        }
        if ($this->hasValidatedKey($validated, 'budgetSyncEnabled', 'budget_sync_enabled')) {
            $payload['budget_sync_enabled'] = (bool) $this->validatedValue($validated, 'budgetSyncEnabled', 'budget_sync_enabled', false);
        }
        if ($this->hasValidatedKey($validated, 'budgetStartYear', 'budget_start_year')) {
            $payload['budget_start_year'] = $this->validatedValue($validated, 'budgetStartYear', 'budget_start_year');
        }
        if ($this->hasValidatedKey($validated, 'budgetStartMonth', 'budget_start_month')) {
            $payload['budget_start_month'] = $this->validatedValue($validated, 'budgetStartMonth', 'budget_start_month');
        }
        if ($this->hasValidatedKey($validated, 'budgetDueDay', 'budget_due_day')) {
            $payload['budget_due_day'] = $this->validatedValue($validated, 'budgetDueDay', 'budget_due_day');
        }
        if ($this->hasValidatedKey($validated, 'paidBudgetPeriods', 'paid_budget_periods')) {
            $periods = $this->validatedValue($validated, 'paidBudgetPeriods', 'paid_budget_periods', []);
            $payload['paid_budget_periods'] = is_array($periods) ? array_values($periods) : [];
        }

        if ($existing === null) {
            $payload['name'] ??= 'Biztosítás';
            $payload['currency'] ??= 'HUF';
            $payload['policy_kind'] ??= 'general';
            $payload['payment_frequency'] ??= 'annual';
            $payload['annual_premium'] ??= 0;
            $payload['payment_amount'] ??= 0;
            $payload['is_active'] ??= true;
            $payload['premium_free'] ??= false;
            $payload['budget_sync_enabled'] ??= false;
        }

        $premiumFree = (bool) ($payload['premium_free'] ?? $existing?->premium_free ?? false);
        if ($premiumFree) {
            $payload['annual_premium'] = 0;
            $payload['payment_amount'] = 0;
            $payload['budget_sync_enabled'] = false;
        } else {
            $paymentAmount = (float) ($payload['payment_amount'] ?? $existing?->payment_amount ?? 0);
            $frequency = (string) ($payload['payment_frequency'] ?? $existing?->payment_frequency ?? 'annual');
            if ($paymentAmount > 0 && ! array_key_exists('annual_premium', $payload)) {
                $payload['annual_premium'] = round($paymentAmount * $this->periodsPerYear($frequency), 2);
            } elseif ($paymentAmount <= 0 && isset($payload['annual_premium'])) {
                $annual = (float) $payload['annual_premium'];
                $periods = $this->periodsPerYear($frequency);
                if ($annual > 0 && $periods > 0) {
                    $payload['payment_amount'] = round($annual / $periods, 2);
                }
            }
        }

        $sync = (bool) ($payload['budget_sync_enabled'] ?? $existing?->budget_sync_enabled ?? false);
        if ($sync && $premiumFree) {
            $payload['budget_sync_enabled'] = false;
        }

        return $this->applyCoverageExpiryActiveState($payload);
    }

    /** @param  array<string, mixed>  $payload */
    private function applyCoverageExpiryActiveState(array $payload): array
    {
        $covered = $payload['covered_until'] ?? null;
        if ($covered && Carbon::parse((string) $covered)->startOfDay()->lt(Carbon::today())) {
            $payload['is_active'] = false;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function formatPolicy(InsurancePolicy $p): array
    {
        $annual = $this->effectiveAnnualPremium($p);
        $periods = $this->periodsPerYear((string) $p->payment_frequency);
        $paymentAmount = (float) $p->payment_amount;
        if ($paymentAmount <= 0 && $annual > 0 && $periods > 0) {
            $paymentAmount = round($annual / $periods, 2);
        }

        return [
            'id' => $p->id,
            'name' => $p->name,
            'insurer' => $p->insurer,
            'policyKind' => (string) ($p->policy_kind ?? 'general'),
            'annualPremium' => round($annual, 2),
            'monthlyPremium' => $annual > 0 ? round($annual / 12, 2) : 0,
            'fundValue' => $p->fund_value !== null ? round((float) $p->fund_value, 2) : null,
            'premiumFree' => (bool) $p->premium_free,
            'paymentFrequency' => (string) ($p->payment_frequency ?? 'annual'),
            'paymentAmount' => round($paymentAmount, 2),
            'currency' => $p->currency,
            'renewalDate' => $p->renewal_date?->toDateString(),
            'coveredUntil' => $p->covered_until?->toDateString(),
            'notes' => $p->notes,
            'isActive' => (bool) $p->is_active,
            'budgetSyncEnabled' => (bool) $p->budget_sync_enabled,
            'budgetStartYear' => $p->budget_start_year,
            'budgetStartMonth' => $p->budget_start_month,
            'budgetDueDay' => $p->budget_due_day,
            'paidBudgetPeriods' => $p->paid_budget_periods ?? [],
            'attachmentCount' => (int) ($p->attachments_count ?? $p->attachments()->count()),
            'deletedAt' => $p->deleted_at?->toIso8601String(),
            'createdAt' => $p->created_at?->toIso8601String(),
            'updatedAt' => $p->updated_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $validated */
    private function hasValidatedKey(array $validated, string $camel, string $snake): bool
    {
        return array_key_exists($camel, $validated) || array_key_exists($snake, $validated);
    }

    /** @param  array<string, mixed>  $validated */
    private function validatedValue(array $validated, string $camel, string $snake, mixed $default = null): mixed
    {
        if (array_key_exists($camel, $validated)) {
            return $validated[$camel];
        }
        if (array_key_exists($snake, $validated)) {
            return $validated[$snake];
        }

        return $default;
    }

    private function effectiveAnnualPremium(InsurancePolicy $p): float
    {
        if ($p->premium_free) {
            return 0.0;
        }
        $payment = (float) $p->payment_amount;
        if ($payment > 0) {
            return $payment * $this->periodsPerYear((string) $p->payment_frequency);
        }

        return (float) $p->annual_premium;
    }

    private function periodsPerYear(string $frequency): int
    {
        return match ($frequency) {
            'monthly' => 12,
            'quarterly' => 4,
            'semiannual' => 2,
            'annual' => 1,
            default => 1,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $policies
     * @return list<array<string, mixed>>
     */
    private function buildUpcomingReminders(array $policies, int $reminderDays): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays($reminderDays);
        $items = [];

        foreach ($policies as $policy) {
            if (! ($policy['isActive'] ?? false)) {
                continue;
            }
            $coveredUntil = $policy['coveredUntil'] ?? null;
            if ($coveredUntil && Carbon::parse((string) $coveredUntil)->startOfDay()->lt($today)) {
                continue;
            }
            $name = (string) ($policy['name'] ?? '');

            foreach ([
                ['date' => $policy['renewalDate'] ?? null, 'kind' => 'renewal', 'label' => 'Megújítás'],
                ['date' => $policy['coveredUntil'] ?? null, 'kind' => 'coverage_end', 'label' => 'Fedezet vége'],
            ] as $row) {
                if (! $row['date']) {
                    continue;
                }
                $date = Carbon::parse($row['date'])->startOfDay();
                if ($date->gt($horizon)) {
                    continue;
                }
                $daysUntil = (int) $today->diffInDays($date, false);
                $items[] = [
                    'policyId' => $policy['id'],
                    'policyName' => $name,
                    'date' => $date->toDateString(),
                    'kind' => $row['kind'],
                    'kindLabel' => $row['label'],
                    'daysUntil' => $daysUntil,
                    'overdue' => $daysUntil < 0,
                ];
            }
        }

        usort($items, fn ($a, $b) => ($a['daysUntil'] ?? 0) <=> ($b['daysUntil'] ?? 0));

        return $items;
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
        abort_unless($household->insurance_enabled, 403, 'A biztosítások modul nincs bekapcsolva.');
    }
}
