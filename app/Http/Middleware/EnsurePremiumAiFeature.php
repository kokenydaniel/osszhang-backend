<?php

namespace App\Http\Middleware;

use App\Services\AiHouseholdPolicyService;
use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremiumAiFeature
{
    public function __construct(
        private readonly AiHouseholdPolicyService $householdPolicy,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! AccessControl::canUseFeature($user, 'ai')) {
            return response()->json([
                'message' => AccessControl::featureAccessDeniedMessage('ai'),
                'code' => 'SUBSCRIPTION_FEATURE_REQUIRED',
            ], 403);
        }

        $household = $user->household;
        if ($household !== null) {
            $denial = $this->householdPolicy->denialReason($household);
            if ($denial !== null) {
                return response()->json([
                    'message' => $denial['message'],
                    'code' => $denial['code'],
                ], 403);
            }
        }

        return $next($request);
    }
}
