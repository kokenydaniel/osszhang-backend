<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;
use App\Support\HouseholdRole;

class DebtPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->household_id !== null;
    }

    public function view(User $user, Debt $debt): bool
    {
        return $this->userCanAccessDebt($user, $debt);
    }

    public function create(User $user): bool
    {
        return $user->household_id !== null && HouseholdRole::canEdit($user);
    }

    public function update(User $user, Debt $debt): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessDebt($user, $debt);
    }

    public function delete(User $user, Debt $debt): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessDebt($user, $debt);
    }

    private function userCanAccessDebt(User $user, Debt $debt): bool
    {
        if ($user->household_id !== $debt->household_id) {
            return false;
        }

        return $debt->wallet?->isAccessibleTo($user) ?? false;
    }
}
