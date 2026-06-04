<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserFeedbackReport extends Model
{
    protected $fillable = [
        'user_id',
        'household_id',
        'category',
        'subject',
        'message',
        'status',
        'user_last_read_at',
        'admin_last_read_at',
        'page_url',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(UserFeedbackReportAttachment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(UserFeedbackReportMessage::class)->orderBy('created_at');
    }

    protected function casts(): array
    {
        return [
            'user_last_read_at' => 'datetime',
            'admin_last_read_at' => 'datetime',
        ];
    }
}
