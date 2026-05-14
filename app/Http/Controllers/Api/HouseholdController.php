<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function show(Request $request)
    {
        return response()->json($request->user()->household->load('users'));
    }

    public function updateInviteCode(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Nincs jogosultságod.'], 403);
        }

        $request->validate(['invite_code' => 'required|string|min:4|unique:households,invite_code']);
        
        $household = $request->user()->household;
        $household->update(['invite_code' => $request->invite_code]);

        return response()->json(['invite_code' => $household->invite_code]);
    }

    public function updateMember(Request $request, \App\Models\User $member)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Nincs jogosultságod.'], 403);
        }

        // Ensure the member is in the same household
        if ($member->household_id !== $request->user()->household_id) {
            return response()->json(['message' => 'A felhasználó nem tagja a háztartásodnak.'], 404);
        }

        $request->validate([
            'role' => 'sometimes|string|in:admin,member',
            'permissions' => 'sometimes|array'
        ]);

        $member->update($request->only(['role', 'permissions']));

        return response()->json($member->fresh());
    }

    public function addMember(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Nincs jogosultságod.'], 403);
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,member',
            'permissions' => 'required|array'
        ]);

        $member = \App\Models\User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'household_id' => $request->user()->household_id,
            'role' => $request->role,
            'permissions' => $request->permissions,
        ]);

        return response()->json($member);
    }

    public function deleteMember(Request $request, \App\Models\User $member)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Nincs jogosultságod.'], 403);
        }

        if ($member->id === $request->user()->id) {
            return response()->json(['message' => 'Magadat nem törölheted.'], 400);
        }

        if ($member->household_id !== $request->user()->household_id) {
            return response()->json(['message' => 'A felhasználó nem tagja a háztartásodnak.'], 404);
        }

        $member->update(['household_id' => null]);

        return response()->json(['message' => 'Tag eltávolítva.']);
    }

    public function updateCategories(Request $request)
    {
        $household = $request->user()->household;
        $household->update([
            'categories' => $request->categories
        ]);

        return response()->json($household->categories);
    }
}
