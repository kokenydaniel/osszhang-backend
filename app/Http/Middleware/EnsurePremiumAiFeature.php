<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumAiFeature
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! AccessControl::canUseFeature($user, 'ai')) {
            return response()->json([
                'message' => 'Az AI funkciók csak Premium előfizetéssel érhetők el.',
            ], 403);
        }

        return $next($request);
    }
}
