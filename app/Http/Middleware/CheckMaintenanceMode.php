<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/login', 'api/register', 'api/cron/shopify-sync', 'up')) {
            return $next($request);
        }

        if (! FeatureFlags::isEnabled('maintenance_mode')) {
            return $next($request);
        }

        $user = $this->resolveAuthenticatedUser($request);
        if ($user !== null && $user->lifetime_admin) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Karbantartás alatt',
            'code' => 'MAINTENANCE_MODE',
        ], 503);
    }

    private function resolveAuthenticatedUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $plainTextToken = $request->bearerToken();
        if ($plainTextToken === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);
        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }
}
