<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use App\Support\HouseholdRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHouseholdEditor
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($request->is('api/me', 'api/me/*', 'api/logout')) {
            return $next($request);
        }

        if ($request->is('api/tools/travel/plan') && $request->isMethod('POST')) {
            $user = $request->user();
            if ($user !== null && AccessControl::canAccessModule($user, 'travel_planner')) {
                return $next($request);
            }
        }

        $user = $request->user();
        if ($user !== null) {
            HouseholdRole::ensureCanEdit($user);
        }

        return $next($request);
    }
}
