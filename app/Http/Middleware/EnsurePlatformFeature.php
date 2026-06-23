<?php

namespace App\Http\Middleware;

use App\Support\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformFeature
{

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user?->lifetime_admin) {
            return $next($request);
        }

        if (! FeatureFlags::isEnabled($feature)) {
            return response()->json([
                'message' => 'Ez a funkció jelenleg nem érhető el.',
            ], 403);
        }

        return $next($request);
    }
}
