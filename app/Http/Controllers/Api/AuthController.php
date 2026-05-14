<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and create/join a household.
     */
    public function register(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'invite_code' => 'required|string|exists:households,invite_code',
        ]);

        $household = Household::where('invite_code', $request->invite_code)->first();

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'household_id' => $household->id,
            'role' => 'member',
            'permissions' => ['budget', 'utilities', 'business', 'meters', 'debts', 'savings'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('household'),
        ]);
    }

    /**
     * Authenticate user and return token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['A megadott adatok nem egyeznek.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('household'),
        ]);
    }

    /**
     * Logout user (Revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sikeres kijelentkezés.'
        ]);
    }

    /**
     * Get current user profile.
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->load('household.users'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        
        $data = [];
        if ($request->has('firstName')) $data['first_name'] = $request->firstName;
        if ($request->has('lastName')) $data['last_name'] = $request->lastName;
        if ($request->has('email')) $data['email'] = $request->email;

        $user->update($data);

        return response()->json([
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'email' => $user->email
        ]);
    }
}
