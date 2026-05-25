<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Support\HouseholdRole;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->household_id !== null;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->userCanAccessTransaction($user, $transaction);
    }

    public function create(User $user): bool
    {
        return $user->household_id !== null && HouseholdRole::canEdit($user);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessTransaction($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return HouseholdRole::canEdit($user)
            && $this->userCanAccessTransaction($user, $transaction);
    }

    private function userCanAccessTransaction(User $user, Transaction $transaction): bool
    {
        if ($user->household_id !== $transaction->household_id) {
            return false;
        }

        if ($transaction->wallet_id === null) {
            return true;
        }

        return $transaction->wallet?->isAccessibleTo($user) ?? false;
    }
}
