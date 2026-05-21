<?php

namespace App\Services;

use App\Http\Resources\HouseholdResource;
use App\Http\Resources\UserResource;
use App\Models\Household;
use App\Models\User;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $validated): array
    {
        $username = Username::normalize($validated['username']);

        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $validated['household_name']);
        if (empty($cleanName)) {
            $cleanName = 'PILOT';
        }
        $inviteCode = strtoupper(substr($cleanName, 0, 5)).rand(100, 999);
        while (Household::where('invite_code', $inviteCode)->exists()) {
            $inviteCode = strtoupper(substr($cleanName, 0, 5)).rand(100, 999);
        }

        $household = Household::create([
            'name' => $validated['household_name'],
            'invite_code' => $inviteCode,
            'budget_enabled' => true,
            'savings_enabled' => false,
            'debts_enabled' => false,
            'utilities_enabled' => false,
            'meters_enabled' => false,
            'business_enabled' => false,
            'business_name' => '',
            'utility_split_enabled' => false,
            'categories' => [
                'Fizetés',
                'Élelmiszer',
                'Rezsi',
            ],
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'username' => $username,
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'household_id' => $household->id,
            'role' => 'admin',
            'permissions' => ['budget'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->buildAuthPayload($user->load('household')),
        ];
    }

    public function login(string $username, string $password): array
    {
        $normalized = Username::normalize($username);
        $user = User::where('username', $normalized)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['A megadott adatok nem egyeznek.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->buildAuthPayload($user->load('household')),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function me(User $user): array
    {
        return $this->buildAuthPayload($user->load('household.users'));
    }

    public function updateProfile(User $user, Request $request): array
    {
        $data = [];
        if ($request->has('firstName')) {
            $data['first_name'] = $request->firstName;
        }
        if ($request->has('lastName')) {
            $data['last_name'] = $request->lastName;
        }
        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
            $data['must_change_password'] = false;
        }

        $user->update($data);

        return [
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }

    public function changePassword(User $user, string $password): array
    {
        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ]);

        return [
            'message' => 'Jelszó sikeresen megváltoztatva.',
            'must_change_password' => false,
        ];
    }

    public function buildAuthPayload(User $user): array
    {
        return array_merge(
            (new UserResource($user))->resolve(),
            [
                'household' => $user->household
                    ? (new HouseholdResource($user->household))->resolve()
                    : null,
            ],
        );
    }
}
