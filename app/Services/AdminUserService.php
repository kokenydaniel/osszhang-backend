<?php

namespace App\Services;

use App\Http\Resources\AdminUserResource;
use App\Models\ImpersonationAudit;
use App\Models\User;
use App\Services\AuthService;
use App\Support\AccessControl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function listUsers(Request $request): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        $lifetimeAdmin = (string) $request->query('lifetime_admin', 'all');
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $query = User::query()
            ->with('household:id,name,business_name,subscription_tier,subscription_status,tier_grant,tier_grant_expires_at,tier_grant_note')
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->where('username', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like);
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($lifetimeAdmin === 'yes') {
            $query->where('lifetime_admin', true);
        } elseif ($lifetimeAdmin === 'no') {
            $query->where('lifetime_admin', false);
        }

        return $query->paginate($perPage);
    }

    public function activate(User $user): array
    {
        if ($user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['A felhasználó már aktív.'],
            ]);
        }

        $user->update(['is_active' => true]);

        return (new AdminUserResource($user->fresh('household')))->resolve();
    }

    public function deactivate(User $actor, User $user): array
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => ['Saját fiókodat nem inaktiválhatod.'],
            ]);
        }

        if ($user->lifetime_admin) {
            throw ValidationException::withMessages([
                'user' => ['Platform admin fiókot nem inaktiválhatsz.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user' => ['A felhasználó már inaktív.'],
            ]);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return (new AdminUserResource($user->fresh('household')))->resolve();
    }

    public function impersonate(User $actor, User $target, Request $request): array
    {
        if ($target->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => ['Nem tudsz saját magadként belépni.'],
            ]);
        }

        if ($target->lifetime_admin) {
            throw ValidationException::withMessages([
                'user' => ['Platform admin felhasználót nem lehet megszemélyesíteni.'],
            ]);
        }

        if (! $target->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Inaktív felhasználót nem lehet megszemélyesíteni.'],
            ]);
        }

        ImpersonationAudit::create([
            'actor_id' => $actor->id,
            'target_user_id' => $target->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
        ]);

        $token = $target->createToken('impersonation:actor_'.$actor->id)->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->authService->buildAuthPayload($target->fresh(['household.users'])),
            'target' => (new AdminUserResource($target->fresh('household')))->resolve(),
        ];
    }

    /** @return array<string, mixed> */
    public function updateTierGrant(User $actor, User $target, Request $request): array
    {
        if ($target->lifetime_admin) {
            throw ValidationException::withMessages([
                'user' => ['Platform admin háztartásán nem állítható be grant.'],
            ]);
        }

        $household = $target->household;
        if ($household === null) {
            throw ValidationException::withMessages([
                'user' => ['A felhasználónak nincs háztartása.'],
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

            return (new AdminUserResource($target->fresh('household')))->resolve();
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

        return (new AdminUserResource($target->fresh('household')))->resolve();
    }
}
