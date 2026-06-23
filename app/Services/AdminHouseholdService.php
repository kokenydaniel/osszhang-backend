<?php

namespace App\Services;

use App\Http\Resources\AdminHouseholdResource;
use App\Models\Household;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminHouseholdService
{
    public function __construct(
        private readonly HouseholdService $householdService,
        private readonly AuditLogService $auditLogService,
        private readonly AiTokenUsageService $aiTokenUsageService,
    ) {}

    public function listHouseholds(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $tier = (string) $request->query('tier', 'all');
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $query = Household::query()
            ->withCount([
                'users',
                'users as active_users_count' => fn ($builder) => $builder->where('is_active', true),
            ])
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->where('name', 'like', $like)
                    ->orWhere('business_name', 'like', $like)
                    ->orWhereHas('users', function ($userQuery) use ($like) {
                        $userQuery
                            ->where('username', 'like', $like)
                            ->orWhere('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            });
        }

        if ($tier === AccessControl::TIER_PRO || $tier === AccessControl::TIER_PREMIUM) {
            $query->where(function ($builder) use ($tier) {
                $builder
                    ->where('subscription_tier', $tier)
                    ->orWhere(function ($grantQuery) use ($tier) {
                        $grantQuery
                            ->where('tier_grant', $tier)
                            ->where(function ($expiryQuery) {
                                $expiryQuery
                                    ->whereNull('tier_grant_expires_at')
                                    ->orWhere('tier_grant_expires_at', '>', now());
                            });
                    });
            });
        } elseif ($tier === AccessControl::TIER_FREE) {
            $query->where(function ($builder) {
                $builder
                    ->where(function ($billingQuery) {
                        $billingQuery
                            ->whereNull('subscription_tier')
                            ->orWhere('subscription_tier', AccessControl::TIER_FREE);
                    })
                    ->where(function ($grantQuery) {
                        $grantQuery
                            ->whereNull('tier_grant')
                            ->orWhere(function ($expiredQuery) {
                                $expiredQuery
                                    ->whereNotNull('tier_grant_expires_at')
                                    ->where('tier_grant_expires_at', '<=', now());
                            });
                    });
            });
        }

        return $query->paginate($perPage);
    }

    public function show(Household $household): array
    {
        $household->loadCount([
            'users',
            'users as active_users_count' => fn ($builder) => $builder->where('is_active', true),
            'wallets',
            'transactions',
            'debts',
            'savings',
            'utilities',
            'meters',
            'businessOrders as business_orders_count',
        ])->load(['users' => fn ($query) => $query->orderBy('role')->orderBy('id')]);

        return (new AdminHouseholdResource(
            $household,
            detailed: true,
            aiUsage: $this->aiTokenUsageService->householdSummary($household->id),
        ))->resolve();
    }

    public function updateTierGrant(User $actor, Household $household, Request $request): array
    {
        if ($household->users()->where('lifetime_admin', true)->exists()) {
            throw ValidationException::withMessages([
                'household' => ['Platform admin háztartásán nem állítható be grant.'],
            ]);
        }

        $grantTier = $request->input('grant_tier');

        if ($grantTier === null || $grantTier === '') {
            $household->update([
                'tier_grant' => null,
                'tier_grant_expires_at' => null,
                'tier_grant_note' => null,
                'tier_grant_granted_by' => null,
            ]);

            return $this->show($household->fresh());
        }

        if (! in_array($grantTier, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)) {
            throw ValidationException::withMessages([
                'grant_tier' => ['Érvénytelen grant szint.'],
            ]);
        }

        $permanent = $request->boolean('permanent');
        $expiresAt = null;

        if (! $permanent) {
            $expiresRaw = $request->input('expires_at');
            if ($expiresRaw === null || $expiresRaw === '') {
                throw ValidationException::withMessages([
                    'expires_at' => ['Adj meg lejárati dátumot, vagy jelöld be az örökös grantot.'],
                ]);
            }
            $expiresAt = Carbon::parse($expiresRaw)->endOfDay();
            if ($expiresAt->isPast()) {
                throw ValidationException::withMessages([
                    'expires_at' => ['A lejárat csak jövőbeli dátum lehet.'],
                ]);
            }
        }

        $household->update([
            'tier_grant' => $grantTier,
            'tier_grant_expires_at' => $expiresAt,
            'tier_grant_note' => $request->input('note'),
            'tier_grant_granted_by' => $actor->id,
        ]);

        return $this->show($household->fresh());
    }

    public function updateAiSettings(User $actor, Household $household, Request $request): array
    {
        $updates = [];

        if ($request->has('usage_blocked')) {
            $updates['ai_usage_blocked'] = $request->boolean('usage_blocked');
        }

        if ($request->exists('monthly_token_limit')) {
            $limit = $request->input('monthly_token_limit');
            $updates['ai_monthly_token_limit'] = $limit === null || $limit === '' ? null : (int) $limit;
        }

        if ($updates !== []) {
            $household->update($updates);

            $this->auditLogService->record(
                'admin.household.ai_settings',
                $actor->id,
                $household->id,
                'household',
                $household->id,
                $updates,
                $request,
            );
        }

        return $this->show($household->fresh());
    }

    public function destroy(User $actor, Household $household, Request $request): void
    {
        if ($household->users()->where('lifetime_admin', true)->exists()) {
            throw ValidationException::withMessages([
                'household' => ['Platform admin háztartása nem törölhető.'],
            ]);
        }

        if (trim((string) $request->input('confirm_name')) !== trim($household->name)) {
            throw ValidationException::withMessages([
                'confirm_name' => ['A megerősítő szövegnek pontosan a háztartás nevét kell beírnod.'],
            ]);
        }

        $householdId = $household->id;
        $householdName = $household->name;
        $memberCount = $household->users()->count();

        $this->householdService->destroyHousehold($household);

        $this->auditLogService->record(
            'admin.household.destroy',
            $actor->id,
            $householdId,
            'household',
            $householdId,
            ['name' => $householdName, 'members_deleted' => $memberCount],
            $request,
        );
    }
}
