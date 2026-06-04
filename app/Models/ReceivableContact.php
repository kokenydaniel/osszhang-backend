<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivableContact extends Model
{
    protected $fillable = [
        'household_id',
        'name',
        'note',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ReceivableEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }
}
