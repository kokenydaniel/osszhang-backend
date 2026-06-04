<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedbackReportMessage extends Model
{
    protected $fillable = [
        'user_feedback_report_id',
        'user_id',
        'author',
        'body',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(UserFeedbackReport::class, 'user_feedback_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
