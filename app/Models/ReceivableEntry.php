<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableEntry extends Model
{
    protected $fillable = [
        'household_id',
        'receivable_contact_id',
        'entry_type',
        'amount',
        'currency',
        'source',
        'entry_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'float',
        'entry_date' => 'date',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ReceivableContact::class, 'receivable_contact_id');
    }
}
