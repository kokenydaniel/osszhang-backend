<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use App\Support\AccessControl;
use App\Support\HouseholdRole;

class WalletPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->household_id !== null;
    }

    public function view(User $user, Wallet $wallet): bool
    {
        return $wallet->isAccessibleTo($user);
    }

    public function create(User $user): bool
    {
        if ($user->household_id === null || ! HouseholdRole::isAdmin($user)) {
            return false;
        }

        $maxWallets = AccessControl::maxWallets($user);
        if ($maxWallets === null) {
            return true;
        }

        $currentCount = Wallet::query()
            ->where('household_id', $user->household_id)
            ->count();

        return $currentCount < $maxWallets;
    }

    public function createPrivate(User $user): bool
    {
        return $this->create($user) && AccessControl::canCreatePrivateWallet($user);
    }

    public function update(User $user, Wallet $wallet): bool
    {
        return $this->canModifyWallet($user, $wallet);
    }

    public function updateManualBalance(User $user, Wallet $wallet): bool
    {
        return $this->canModifyWallet($user, $wallet);
    }

    public function delete(User $user, Wallet $wallet): bool
    {
        if ($wallet->is_shared) {
            return false;
        }

        return $wallet->household_id === $user->household_id
            && $wallet->owner_id === $user->id;
    }

    /**
     * Privát kassza: csak a tulajdonos módosíthat.
     * Közös kassza: háztartás-tag szerkesztő vagy admin (olvasó nem).
     */
    private function canModifyWallet(User $user, Wallet $wallet): bool
    {
        if ($wallet->household_id !== $user->household_id || ! $wallet->isAccessibleTo($user)) {
            return false;
        }

        if ($wallet->is_shared) {
            return HouseholdRole::canEdit($user);
        }

        return $wallet->owner_id === $user->id;
    }
}
