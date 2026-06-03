<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PocketMoneyEntry extends Model
{
    protected $fillable = [
        'household_id', 'member_user_id', 'member_label', 'entry_type',
        'amount', 'currency', 'entry_date', 'note',
    ];

    protected $casts = [
        'amount' => 'float',
        'entry_date' => 'date',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function memberUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }
}
