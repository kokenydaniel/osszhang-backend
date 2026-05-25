<?php

namespace App\Policies;

use App\Models\Saving;
use App\Models\User;
use App\Support\HouseholdRole;

class SavingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->household_id !== null;
    }

    public function view(User $user, Saving $saving): bool
    {
        return $this->userCanAccessSaving($user, $saving);
    }

    public function create(User $user): bool
    {
        return $user->household_id !== null && HouseholdRole::canEdit($user);
    }

    public function update(User $user, Saving $saving): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessSaving($user, $saving);
    }

    public function delete(User $user, Saving $saving): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessSaving($user, $saving);
    }

    private function userCanAccessSaving(User $user, Saving $saving): bool
    {
        if ($user->household_id !== $saving->household_id) {
            return false;
        }

        return $saving->wallet?->isAccessibleTo($user) ?? false;
    }
}
