<?php

namespace App\Services;

use App\Models\Saving;
use App\Models\TravelPlan;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;

class TravelPlanService
{
    public function __construct(
        private readonly WalletProvisioningService $wallets,
    ) {}

    public function listForUser(User $user, ?int $walletId = null, int $limit = 20): array
    {
        $this->requireHousehold($user);

        $query = TravelPlan::query()
            ->accessibleTo($user)
            ->with(['wallet', 'linkedSaving'])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($walletId !== null) {
            $query->where('wallet_id', $walletId);
        }

        return $query->get()->map(fn (TravelPlan $plan) => $this->format($plan))->all();
    }

    public function show(User $user, int $id): array
    {
        $this->requireHousehold($user);

        return $this->format($this->findAccessible($user, $id));
    }

    public function store(
        User $user,
        array $input,
        array $planPayload,
        ?array $financialContext = null,
        ?int $walletId = null,
    ): array {
        $household = $this->requireHousehold($user);
        $wallet = $this->resolveWallet($user, $walletId);

        $plan = TravelPlan::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'destination' => (string) $input['destination'],
            'origin_location' => $input['origin_location'] ?? null,
            'duration_days' => (int) $input['duration_days'],
            'travelers_count' => (int) ($input['travelers_count'] ?? 1),
            'total_budget' => (float) $input['total_budget'],
            'target_date' => $input['target_date'] ?? null,
            'trip_style' => (string) ($input['trip_style'] ?? 'mixed'),
            'accommodation_preference' => (string) ($input['accommodation_preference'] ?? 'mixed'),
            'transport_mode' => (string) ($input['transport_mode'] ?? 'mixed'),
            'transport_already_booked' => (bool) ($input['transport_already_booked'] ?? false),
            'accommodation_already_booked' => (bool) ($input['accommodation_already_booked'] ?? false),
            'car_fuel_consumption_l100' => isset($input['car_fuel_consumption_l100'])
                ? (float) $input['car_fuel_consumption_l100']
                : null,
            'plan_payload' => $planPayload,
            'input_payload' => $input,
            'financial_context' => $financialContext,
        ]);

        return $this->format($plan->fresh(['wallet', 'linkedSaving']));
    }

    public function destroy(User $user, int $id): void
    {
        $this->requireHousehold($user);
        $this->findAccessible($user, $id)->delete();
    }

    public function linkSavingGoal(User $user, int $planId, int $savingId): array
    {
        $this->requireHousehold($user);
        $plan = $this->findAccessible($user, $planId);
        $saving = Saving::query()->accessibleTo($user)->findOrFail($savingId);

        $plan->update(['saving_id' => $saving->id]);
        $saving->update(['travel_plan_id' => $plan->id]);

        return $this->format($plan->fresh(['wallet', 'linkedSaving']));
    }

    public function updateCostAdjustments(User $user, int $id, array $payload): array
    {
        $this->requireHousehold($user);
        $plan = $this->findAccessible($user, $id);
        $planPayload = $plan->plan_payload ?? [];

        $planPayload['cost_line_items'] = $payload['cost_line_items'];
        $planPayload['total_estimated_cost'] = (float) $payload['total_estimated_cost'];
        $planPayload['remaining_to_pay_huf'] = (float) $payload['remaining_to_pay_huf'];
        $planPayload['paid_total_huf'] = (float) ($payload['paid_total_huf'] ?? 0);
        $planPayload['cost_breakdown'] = $payload['cost_breakdown'];
        if (isset($payload['financial_fit']) && is_array($payload['financial_fit'])) {
            $planPayload['financial_fit'] = $payload['financial_fit'];
        }

        $plan->update(['plan_payload' => $planPayload]);

        return $this->format($plan->fresh(['wallet', 'linkedSaving']));
    }

    public function format(TravelPlan $plan): array
    {
        $payload = $plan->plan_payload ?? [];

        return [
            'id' => $plan->id,
            'destination' => $plan->destination,
            'origin_location' => $plan->origin_location,
            'duration_days' => $plan->duration_days,
            'travelers_count' => $plan->travelers_count,
            'total_budget' => round((float) $plan->total_budget, 2),
            'target_date' => $plan->target_date?->toDateString(),
            'trip_style' => $plan->trip_style,
            'accommodation_preference' => $plan->accommodation_preference,
            'transport_mode' => $plan->transport_mode,
            'transport_already_booked' => (bool) $plan->transport_already_booked,
            'accommodation_already_booked' => (bool) $plan->accommodation_already_booked,
            'car_fuel_consumption_l100' => $plan->car_fuel_consumption_l100,
            'plan' => $payload,
            'input' => $plan->input_payload,
            'financial_context' => $plan->financial_context,
            'saving_id' => $plan->saving_id,
            'wallet_id' => $plan->wallet_id,
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
            'summary' => $payload['summary'] ?? null,
            'total_estimated_cost' => (float) ($payload['total_estimated_cost'] ?? 0),
        ];
    }

    private function findAccessible(User $user, int $id): TravelPlan
    {
        return TravelPlan::query()->accessibleTo($user)->findOrFail($id);
    }

    private function resolveWallet(User $user, ?int $walletId): Wallet
    {
        if ($walletId !== null) {
            return Wallet::query()
                ->accessibleTo($user)
                ->where('household_id', $user->household_id)
                ->findOrFail($walletId);
        }

        return $this->wallets->sharedWalletForHousehold($user->household);
    }

    private function requireHousehold(User $user)
    {
        if ($user->household === null) {
            throw new AuthorizationException('Ehhez a művelethez háztartás szükséges.');
        }

        return $user->household;
    }
}
