<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\ActivitySubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic student submission for an Activity.
 *
 * Attempt/version and payload are stored without grading, scoring, or code execution.
 */
#[Fillable([
    'enrollment_id',
    'user_id',
    'activity_id',
    'attempt_number',
    'version',
    'status',
    'payload',
    'submitted_at',
])]
class ActivitySubmission extends Model
{
    /** @use HasFactory<ActivitySubmissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'version' => 'integer',
            'status' => SubmissionStatus::class,
            'payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
