<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTierModuleAccess
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if ($user === null || ! AccessControl::canAccessModule($user, $module)) {
            return response()->json([
                'message' => AccessControl::moduleAccessDeniedMessage($module),
                'code' => 'SUBSCRIPTION_MODULE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}
