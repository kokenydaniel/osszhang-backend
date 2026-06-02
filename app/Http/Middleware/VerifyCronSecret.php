<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.cron.secret');
        if (! is_string($expected) || $expected === '') {
            abort(503, 'Cron is not configured.');
        }

        $provided = $request->header('X-Cron-Secret', '');
        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
