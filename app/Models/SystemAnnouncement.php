<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemAnnouncement extends Model
{
    protected $fillable = [
        'message',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
