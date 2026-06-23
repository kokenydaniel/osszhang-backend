<?php

namespace App\Services;

use App\Http\Resources\WalletResource;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UtilitySettlement;
use App\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(
        private readonly UtilitySettlementService $utilitySettlements,
    ) {}

    public function listAccessible(User $user): array
    {
        if ($user->household_id === null) {
            return [];
        }

        return Wallet::query()
            ->accessibleTo($user)
            ->orderByDesc('is_shared')
            ->orderBy('name')
            ->get()
            ->map(fn (Wallet $wallet) => (new WalletResource($wallet))->resolve())
            ->all();
    }

    public function create(User $user, array $validated): array
    {
        $isShared = (bool) ($validated['isShared'] ?? false);

        if ($isShared) {
            throw ValidationException::withMessages([
                'isShared' => ['A közös kassza automatikusan létrejön. Privát kasszát hozhatsz létre.'],
            ]);
        }

        Gate::authorize('createPrivate', Wallet::class);
        Gate::authorize('create', Wallet::class);

        $ownerId = isset($validated['ownerId']) ? (int) $validated['ownerId'] : $user->id;

        if ($ownerId !== $user->id && $user->role !== 'admin') {
            throw new AuthorizationException('Csak admin hozhat létre kasszát más tag számára.');
        }

        $owner = User::query()
            ->where('id', $ownerId)
            ->where('household_id', $user->household_id)
            ->first();

        if ($owner === null) {
            throw ValidationException::withMessages([
                'ownerId' => ['A tulajdonos nem tagja ennek a háztartásnak.'],
            ]);
        }

        $wallet = Wallet::create([
            'household_id' => $user->household_id,
            'name' => $validated['name'],
            'is_shared' => false,
            'owner_id' => $ownerId,
        ]);

        return (new WalletResource($wallet))->resolve();
    }

    public function updateManualBalance(User $user, Wallet $wallet, float $manualBalance): array
    {
        Gate::authorize('updateManualBalance', $wallet);

        if ($wallet->household_id !== $user->household_id) {
            throw new AuthorizationException();
        }

        $wallet->update(['manual_balance' => $manualBalance]);

        return (new WalletResource($wallet->fresh()))->resolve();
    }

    public function update(User $user, Wallet $wallet, array $validated): array
    {
        Gate::authorize('update', $wallet);

        if ($wallet->household_id !== $user->household_id) {
            throw new AuthorizationException();
        }

        $wallet->update(['name' => $validated['name']]);

        return (new WalletResource($wallet->fresh()))->resolve();
    }

    public function delete(User $user, Wallet $wallet): void
    {
        Gate::authorize('delete', $wallet);

        if ($wallet->household_id !== $user->household_id) {
            throw new AuthorizationException();
        }

        $household = $user->household;
        if ($household === null) {
            throw new AuthorizationException();
        }

        DB::transaction(function () use ($wallet, $household) {
            $transactionIds = $wallet->transactions()->pluck('id');

            if ($transactionIds->isNotEmpty()) {
                UtilitySettlement::query()
                    ->where('household_id', $household->id)
                    ->whereIn('transaction_id', $transactionIds)
                    ->get()
                    ->each(fn (UtilitySettlement $settlement) => $this->utilitySettlements->revert(
                        $settlement,
                        $household,
                        false,
                    ));

                Transaction::query()->whereIn('id', $transactionIds)->delete();
            }

            Saving::query()->where('wallet_id', $wallet->id)->delete();
            $wallet->debts()->delete();
            $wallet->delete();
        });
    }

    public function findAccessible(User $user, int $walletId): Wallet
    {
        return Wallet::query()
            ->accessibleTo($user)
            ->where('household_id', $user->household_id)
            ->findOrFail($walletId);
    }
}
