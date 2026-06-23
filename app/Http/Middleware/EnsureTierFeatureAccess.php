<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTierFeatureAccess
{

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user === null || ! AccessControl::canUseFeature($user, $feature)) {
            return response()->json([
                'message' => AccessControl::featureAccessDeniedMessage($feature),
                'code' => 'SUBSCRIPTION_FEATURE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
