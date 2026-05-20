<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\UserResource;
use App\Models\Household;
use App\Models\User;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $username = Username::normalize($request->input('username', ''));

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'password' => 'required|string|min:8|confirmed',
            'household_name' => 'required|string|max:255',
        ]);

        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $request->household_name);
        if (empty($cleanName)) {
            $cleanName = 'PILOT';
        }
        $inviteCode = strtoupper(substr($cleanName, 0, 5)).rand(100, 999);
        while (Household::where('invite_code', $inviteCode)->exists()) {
            $inviteCode = strtoupper(substr($cleanName, 0, 5)).rand(100, 999);
        }

        $household = Household::create([
            'name' => $request->household_name,
            'invite_code' => $inviteCode,
            'categories' => [
                'Fizetés',
                'Kaja',
                'Tankolás',
                'Rezsi',
                'Kölcsönök',
                'Szórakozás',
                'Megtakarítás',
                'Vállalkozás',
            ],
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $username,
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'household_id' => $household->id,
            'role' => 'admin',
            'permissions' => ['budget', 'utilities', 'business', 'meters', 'debts', 'savings'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user->load('household')),
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3|max:255',
            'password' => 'required',
        ]);

        $username = Username::normalize($request->username);
        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['A megadott adatok nem egyeznek.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authUserPayload($user->load('household')),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sikeres kijelentkezés.',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('household.users');

        return response()->json($this->authUserPayload($user));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

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

        return response()->json([
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return response()->json([
            'message' => 'Jelszó sikeresen megváltoztatva.',
            'must_change_password' => false,
        ]);
    }

    private function authUserPayload(User $user): array
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
