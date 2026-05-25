<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Household;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class DebtService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
        private readonly WalletProvisioningService $wallets,
    ) {}

    /** @return list<array<string, mixed>> */
    public function listForUser(User $user, ?int $walletId = null): array
    {
        $household = $this->requireHousehold($user);

        return $this->accessibleDebtsQuery($user, $walletId)
            ->get()
            ->map(fn ($d) => $this->crypto->formatDebt($d, $household))
            ->all();
    }

    /** @return array<string, mixed> */
    public function create(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $wallet = $this->resolveWalletForMutation(
            $user,
            isset($validated['walletId']) ? (int) $validated['walletId'] : null,
        );

        $d = new Debt([
            'household_id' => $household->id,
            'wallet_id' => $wallet->id,
        ]);
        $this->crypto->persistDebt($d, $household, [
            'name' => $validated['name'],
            'target_amount' => (float) $validated['targetAmount'],
            'paid_amount' => (float) ($validated['paidAmount'] ?? 0),
            'annual_interest_rate' => $validated['annualInterestRate'] ?? null,
            'minimum_payment' => $validated['minimumPayment'] ?? null,
            'due_day' => $validated['dueDay'] ?? null,
            'status' => $validated['status'] ?? 'Még fizetendő',
        ]);
        $d->save();

        return $this->crypto->formatDebt($d, $household);
    }

    /** @return array<string, mixed> */
    public function update(User $user, int|string $id, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $d = $this->findAccessibleDebt($user, $id);

        $sensitive = $this->crypto->debtResolved($d, $household);
        if (array_key_exists('name', $validated)) {
            $sensitive['name'] = $validated['name'];
        }
        if (array_key_exists('targetAmount', $validated)) {
            $sensitive['target_amount'] = (float) $validated['targetAmount'];
        }
        if (array_key_exists('paidAmount', $validated)) {
            $sensitive['paid_amount'] = (float) $validated['paidAmount'];
        }
        if (array_key_exists('annualInterestRate', $validated)) {
            $sensitive['annual_interest_rate'] = $validated['annualInterestRate'];
        }
        if (array_key_exists('minimumPayment', $validated)) {
            $sensitive['minimum_payment'] = $validated['minimumPayment'];
        }
        if (array_key_exists('dueDay', $validated)) {
            $sensitive['due_day'] = $validated['dueDay'];
        }
        if (array_key_exists('status', $validated)) {
            $sensitive['status'] = $validated['status'];
        }

        $this->crypto->persistDebt($d, $household, $sensitive);
        $d->save();

        return $this->crypto->formatDebt($d, $household);
    }

    public function delete(User $user, int|string $id): void
    {
        $this->findAccessibleDebt($user, $id)->delete();
    }

    private function requireHousehold(User $user): Household
    {
        if ($user->household === null) {
            throw new AuthorizationException('Nincs háztartás a felhasználóhoz rendelve.');
        }

        return $user->household;
    }

    /** @return Builder<Debt> */
    private function accessibleDebtsQuery(User $user, ?int $walletId = null): Builder
    {
        $query = Debt::query()->accessibleTo($user);

        if ($walletId !== null) {
            $query->where('wallet_id', $walletId);
        }

        return $query;
    }

    private function findAccessibleDebt(User $user, int|string $id): Debt
    {
        return $this->accessibleDebtsQuery($user)->findOrFail($id);
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
