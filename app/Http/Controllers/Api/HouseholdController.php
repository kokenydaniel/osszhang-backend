<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\EncryptedRecordService;
use App\Support\BusinessSettings;
use App\Support\Username;
use App\Support\UtilityTemplates;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function show(Request $request)
    {
        return new HouseholdResource($request->user()->household->load('users'));
    }

    public function update(Request $request)
    {
        $household = $request->user()->household;
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'manual_balance' => 'sometimes|numeric',
            'business_enabled' => 'sometimes|boolean',
            'business_name' => 'sometimes|string|max:255',
            'shopify_shop_url' => 'sometimes|nullable|string|max:255',
            'shopify_access_token' => 'sometimes|nullable|string|max:4096',
            'utility_split_enabled' => 'sometimes|boolean',
            'utility_split_partner_id' => 'sometimes|nullable|integer|exists:users,id',
            'business_settings' => 'sometimes|array',
            'business_settings.channels' => 'sometimes|array|min:1',
            'business_settings.channels.*' => 'string|max:100',
            'business_settings.payment_methods' => 'sometimes|array|min:1',
            'business_settings.payment_methods.*' => 'string|max:100',
            'business_settings.providers' => 'sometimes|array|min:1',
            'business_settings.providers.*' => 'string|max:100',
            'business_settings.destinations' => 'sometimes|array|min:1',
            'business_settings.destinations.*' => 'string|max:100',
            'utility_templates' => 'sometimes|array',
            'utility_templates.*.type' => 'required|string|max:100',
            'utility_templates.*.total' => 'sometimes|numeric|min:0',
            'utility_templates.*.due_day' => 'sometimes|integer|min:1|max:28',
            'utility_templates.*.split_rule' => 'sometimes|in:shared,dani-private,ildi-private',
        ]);

        // If a partner is selected, ensure they belong to the same household
        if ($request->has('utility_split_partner_id') && $request->utility_split_partner_id !== null) {
            $partner = \App\Models\User::find($request->utility_split_partner_id);
            if (!$partner || $partner->household_id !== $household->id) {
                return response()->json(['message' => 'A kiválasztott tag nem része ennek a háztartásnak.'], 422);
            }
        }

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        $householdSensitive = null;
        if ($request->has('manual_balance') || $request->exists('utility_templates')) {
            $householdSensitive = $this->crypto->householdSensitive($household);
        }
        if ($request->has('manual_balance')) {
            $householdSensitive['manual_balance'] = (float) $request->manual_balance;
        }
        if ($request->has('business_enabled')) {
            $data['business_enabled'] = $request->boolean('business_enabled');
        }
        if ($request->has('business_name')) {
            $data['business_name'] = $request->business_name;
        }
        if ($request->has('shopify_shop_url')) {
            $data['shopify_shop_url'] = $request->shopify_shop_url;
        }
        if ($request->has('utility_split_enabled')) {
            $data['utility_split_enabled'] = $request->boolean('utility_split_enabled');
        }
        if ($request->has('utility_split_partner_id')) {
            $data['utility_split_partner_id'] = $request->utility_split_partner_id;
        }
        if ($request->has('business_settings')) {
            $data['business_settings'] = BusinessSettings::resolve($request->business_settings);
        }
        if ($request->exists('utility_templates')) {
            $householdSensitive = $householdSensitive ?? $this->crypto->householdSensitive($household);
            $householdSensitive['utility_templates'] = UtilityTemplates::resolve($request->input('utility_templates', []));
        }
        if ($request->filled('shopify_access_token')) {
            $token = trim($request->shopify_access_token);
            if (! str_starts_with($token, 'shpat_') && ! str_starts_with($token, 'shpua_')) {
                return response()->json([
                    'message' => 'A Shopify Admin API token shpat_ vagy shpua_ előtaggal kezdődik.',
                    'errors' => ['shopify_access_token' => ['Érvénytelen token formátum.']],
                ], 422);
            }
            $data['shopify_access_token'] = $token;
        }

        if ($householdSensitive !== null) {
            $this->crypto->persistHouseholdSensitive($household, $householdSensitive);
        }

        if (! empty($data)) {
            $household->update($data);
        } elseif ($householdSensitive !== null) {
            $household->saveQuietly();
        }

        return new HouseholdResource($household->fresh()->load('users'));
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
            'role' => 'sometimes|string|in:admin,editor,reader',
            'permissions' => 'sometimes|array'
        ]);

        $member->update($request->only(['role', 'permissions']));

        return new UserResource($member->fresh());
    }

    public function addMember(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Nincs jogosultságod.'], 403);
        }

        $username = Username::normalize($request->input('username', ''));

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,editor,reader',
            'permissions' => 'required|array',
        ]);

        $member = \App\Models\User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $username,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'must_change_password' => true,
            'household_id' => $request->user()->household_id,
            'role' => $request->role,
            'permissions' => $request->permissions,
        ]);

        return new UserResource($member);
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

        $household = $request->user()->household;
        if ($household->utility_split_partner_id === $member->id) {
            $household->update(['utility_split_partner_id' => null]);
        }

        $member->tokens()->delete();
        $member->delete();

        return response()->json(['message' => 'Tag fiókja törölve.']);
    }

    public function updateCategories(Request $request)
    {
        $household = $request->user()->household;
        $household->update([
            'categories' => $request->categories
        ]);

        return response()->json($household->categories);
    }

    /**
     * Teljes háztartás törlése: minden adat + minden felhasználói fiók.
     */
    public function destroy(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Csak az adminisztrátor törölheti a háztartást.'], 403);
        }

        $household = $request->user()->household;

        $request->validate([
            'confirm_name' => 'required|string',
        ]);

        if (trim($request->confirm_name) !== trim($household->name)) {
            return response()->json([
                'message' => 'A megerősítő szövegnek pontosan a háztartás nevét kell beírnod.',
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($household) {
            $household->update(['utility_split_partner_id' => null]);

            $household->users()->each(function (User $user) {
                $user->tokens()->delete();
                $user->delete();
            });

            $household->delete();
        });

        return response()->json(null, 204);
    }
}
