<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeedbackReportAttachment extends Model
{
    protected $fillable = [
        'user_feedback_report_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(UserFeedbackReport::class, 'user_feedback_report_id');
    }
}
