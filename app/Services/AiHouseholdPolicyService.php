<?php

namespace App\Services;

use App\Models\Household;

class AiHouseholdPolicyService
{
    public function __construct(
        private readonly AiTokenUsageService $tokenUsage,
    ) {}

    /** @return array{code: string, message: string}|null */
    public function denialReason(Household $household): ?array
    {
        if ($household->ai_usage_blocked) {
            return [
                'code' => 'AI_USAGE_BLOCKED',
                'message' => 'Az AI funkciók ehhez a háztartáshoz admin által le vannak tiltva.',
            ];
        }

        $limit = $household->ai_monthly_token_limit;
        if ($limit !== null && $limit > 0) {
            $monthlyTokens = $this->tokenUsage->monthlyTotalTokens($household->id);
            if ($monthlyTokens >= $limit) {
                return [
                    'code' => 'AI_MONTHLY_LIMIT_EXCEEDED',
                    'message' => 'A háztartás elérte a havi AI token limitet. Próbáld újra a következő hónapban, vagy vedd fel a kapcsolatot az ügyfélszolgálattal.',
                ];
            }
        }

        return null;
    }

    public function assertCanUse(Household $household): void
    {
        $reason = $this->denialReason($household);
        if ($reason === null) {
            return;
        }

        abort(response()->json([
            'message' => $reason['message'],
            'code' => $reason['code'],
        ], 403));
    }
}
