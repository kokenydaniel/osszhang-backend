<?php

namespace App\Support;

final class AiUsageContext
{
    public function __construct(
        public readonly ?int $householdId,
        public readonly ?int $userId,
        public readonly string $feature,
    ) {}
}
