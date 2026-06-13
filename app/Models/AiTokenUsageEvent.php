<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTokenUsageEvent extends Model
{
    protected $fillable = [
        'household_id',
        'user_id',
        'feature',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cached_tokens',
        'reasoning_tokens',
        'total_tokens',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_usd' => 'float',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
