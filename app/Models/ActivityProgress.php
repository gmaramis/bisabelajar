<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Database\Factories\ActivityProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Student start/participation state for an Activity.
 *
 * Distinct from Learning Unit progress. Completed is not mastered.
 * Submission and completion rules are later work.
 */
#[Fillable([
    'enrollment_id',
    'user_id',
    'activity_id',
    'status',
    'started_at',
    'completed_at',
])]
class ActivityProgress extends Model
{
    /** @use HasFactory<ActivityProgressFactory> */
    use HasFactory;

    protected $table = 'activity_progress';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function isStarted(): bool
    {
        return $this->status !== ProgressStatus::NotStarted;
    }

    public static function statusFor(?self $progress): ProgressStatus
    {
        return $progress?->status ?? ProgressStatus::NotStarted;
    }

    public static function markStarted(Enrollment $enrollment, Activity $activity): self
    {
        $progress = static::query()->firstOrNew([
            'enrollment_id' => $enrollment->id,
            'activity_id' => $activity->id,
        ]);

        $progress->user_id = $enrollment->user_id;

        if ($progress->status !== ProgressStatus::Completed) {
            $progress->status = ProgressStatus::InProgress;
            $progress->started_at ??= now();
        }

        $progress->save();

        return $progress;
    }
}
