<?php

namespace App\Services;

use App\Http\Requests\Household\AddMemberRequest;
use App\Http\Requests\Household\DestroyHouseholdRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Http\Requests\Household\UpdateInviteCodeRequest;
use App\Http\Requests\Household\UpdateMemberRequest;
use App\Http\Resources\UserResource;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessControl;
use App\Support\BusinessSettings;
use App\Support\DebtsSettings;
use App\Support\MetersSettings;
use App\Support\SavingsSettings;
use App\Support\UtilityTemplates;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class HouseholdService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function show(Household $household): Household
    {
        return $household->load('users');
    }

    public function update(Household $household, UpdateHouseholdRequest $request, User $user): Household
    {
        $this->assertTierAllowsModuleSettings($user, $request);

        if ($request->has('utility_split_partner_id') && $request->utility_split_partner_id !== null) {
            $partner = User::find($request->utility_split_partner_id);
            if (! $partner || $partner->household_id !== $household->id) {
                throw new HttpResponseException(response()->json([
                    'message' => 'A kiválasztott tag nem része ennek a háztartásnak.',
                ], 422));
            }
        }

        $data = [];
        $householdSensitive = null;

        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        if ($request->has('manual_balance')) {
            app(WalletProvisioningService::class)
                ->sharedWalletForHousehold($household)
                ->update(['manual_balance' => (float) $request->manual_balance]);
        }
        foreach (['budget', 'savings', 'debts', 'utilities', 'meters', 'business'] as $module) {
            $key = "{$module}_enabled";
            if ($request->has($key)) {
                $data[$key] = $request->boolean($key);
            }
        }
        if ($request->has('onboarding_completed')) {
            $data['onboarding_completed'] = $request->boolean('onboarding_completed');
        }
        if ($request->has('business_name')) {
            $data['business_name'] = $request->business_name;
        }
        if ($request->has('shopify_import_enabled')) {
            $data['shopify_import_enabled'] = $request->boolean('shopify_import_enabled');
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
        if ($request->has('savings_settings')) {
            $data['savings_settings'] = SavingsSettings::resolve($request->savings_settings);
        }
        if ($request->has('debts_settings')) {
            $data['debts_settings'] = DebtsSettings::resolve($request->debts_settings);
        }
        if ($request->has('meters_settings')) {
            $data['meters_settings'] = MetersSettings::resolve($request->meters_settings);
        }
        if ($request->exists('utility_templates')) {
            $householdSensitive = $householdSensitive ?? $this->crypto->householdSensitive($household);
            $householdSensitive['utility_templates'] = UtilityTemplates::resolve($request->input('utility_templates', []));
        }
        if ($request->filled('shopify_access_token')) {
            $token = trim($request->shopify_access_token);
            if (! str_starts_with($token, 'shpat_') && ! str_starts_with($token, 'shpua_')) {
                throw new HttpResponseException(response()->json([
                    'message' => 'A Shopify Admin API token shpat_ vagy shpua_ előtaggal kezdődik.',
                    'errors' => ['shopify_access_token' => ['Érvénytelen token formátum.']],
                ], 422));
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

        return $household->fresh()->load('users');
    }

    private function assertTierAllowsModuleSettings(User $user, UpdateHouseholdRequest $request): void
    {
        $moduleLabels = [
            'savings' => 'Megtakarítás',
            'debts' => 'Tartozások',
            'utilities' => 'Rezsi',
            'meters' => 'Közműórák',
            'business' => 'Vállalkozás',
        ];

        foreach (['budget', 'savings', 'debts', 'utilities', 'meters', 'business'] as $module) {
            $key = "{$module}_enabled";
            if ($request->has($key) && $request->boolean($key) && ! AccessControl::canAccessModule($user, $module)) {
                $label = $moduleLabels[$module] ?? $module;
                throw new HttpResponseException(response()->json([
                    'message' => "A(z) {$label} modul nem érhető el a jelenlegi csomagodban.",
                    'errors' => [$key => ['Előfizetés szükséges.']],
                ], 422));
            }
        }

        if ($request->has('shopify_import_enabled') && $request->boolean('shopify_import_enabled') && ! AccessControl::canUseFeature($user, 'shopify_import')) {
            throw new HttpResponseException(response()->json([
                'message' => 'A Shopify import nem érhető el a jelenlegi csomagodban.',
                'errors' => ['shopify_import_enabled' => ['Premium előfizetés szükséges.']],
            ], 422));
        }

        if ($request->has('utility_split_enabled') && $request->boolean('utility_split_enabled') && ! AccessControl::canUseFeature($user, 'utility_split')) {
            throw new HttpResponseException(response()->json([
                'message' => 'A rezsi megosztás nem érhető el a jelenlegi csomagodban.',
                'errors' => ['utility_split_enabled' => ['Pro előfizetés szükséges.']],
            ], 422));
        }
    }

    public function updateInviteCode(User $actor, UpdateInviteCodeRequest $request): array
    {
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nincs jogosultságod.');
        }

        $household = $actor->household;
        $household->update(['invite_code' => $request->invite_code]);

        return ['invite_code' => $household->invite_code];
    }

    public function updateMember(User $actor, User $member, UpdateMemberRequest $request): array
    {
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nincs jogosultságod.');
        }

        if ($member->household_id !== $actor->household_id) {
            throw new NotFoundHttpException('A felhasználó nem tagja a háztartásodnak.');
        }

        $member->update($request->only(['role', 'permissions']));

        return (new UserResource($member->fresh()))->resolve();
    }

    public function addMember(User $actor, AddMemberRequest $request): array
    {
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nincs jogosultságod.');
        }

        $member = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'must_change_password' => true,
            'household_id' => $actor->household_id,
            'role' => $request->role,
            'permissions' => $request->permissions,
        ]);

        return (new UserResource($member))->resolve();
    }

    public function deleteMember(User $actor, User $member): array
    {
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Nincs jogosultságod.');
        }

        if ($member->id === $actor->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Magadat nem törölheted.',
            ], 400));
        }

        if ($member->household_id !== $actor->household_id) {
            throw new NotFoundHttpException('A felhasználó nem tagja a háztartásodnak.');
        }

        $household = $actor->household;
        if ($household->utility_split_partner_id === $member->id) {
            $household->update(['utility_split_partner_id' => null]);
        }

        $member->tokens()->delete();
        $member->delete();

        return ['message' => 'Tag fiókja törölve.'];
    }

    public function updateCategories(Household $household, array $categories): array
    {
        $household->update(['categories' => $categories]);

        return $household->categories;
    }

    public function destroy(User $actor, DestroyHouseholdRequest $request): void
    {
        if ($actor->role !== 'admin') {
            throw new AuthorizationException('Csak az adminisztrátor törölheti a háztartást.');
        }

        $household = $actor->household;

        if (trim($request->confirm_name) !== trim($household->name)) {
            throw new HttpResponseException(response()->json([
                'message' => 'A megerősítő szövegnek pontosan a háztartás nevét kell beírnod.',
            ], 422));
        }

        DB::transaction(function () use ($household) {
            $household->update(['utility_split_partner_id' => null]);

            $household->users()->each(function (User $user) {
                $user->tokens()->delete();
                $user->delete();
            });

            $household->delete();
        });
    }
}
