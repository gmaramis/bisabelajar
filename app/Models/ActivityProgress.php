<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use App\Models\LearningEvent;
use Database\Factories\ActivityProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Student participation/completion state for an Activity.
 *
 * Distinct from Learning Unit progress. Completed is not mastered.
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

    public function isCompleted(): bool
    {
        return $this->status === ProgressStatus::Completed;
    }

    public static function statusFor(?self $progress): ProgressStatus
    {
        return $progress?->status ?? ProgressStatus::NotStarted;
    }

    public static function markStarted(Enrollment $enrollment, Activity $activity, bool $emitEvent = true): self
    {
        $progress = static::query()->firstOrNew([
            'enrollment_id' => $enrollment->id,
            'activity_id' => $activity->id,
        ]);

        $progress->user_id = $enrollment->user_id;

        $wasNotStarted = $progress->status === ProgressStatus::NotStarted || $progress->status === null;

        if ($wasNotStarted) {
            $progress->status = ProgressStatus::InProgress;
            $progress->started_at ??= now();
        } else if ($progress->status !== ProgressStatus::Completed) {
            $progress->status = ProgressStatus::InProgress;
        }

        $progress->save();

        // Record activity_started learning event ONLY when actually starting
        if ($emitEvent && $wasNotStarted) {
            LearningEvent::record(
                'activity_started',
                $enrollment->user_id,
                $enrollment->course_id,
                $activity->id,
                [
                    'enrollment_id' => $enrollment->id,
                    'progress_status' => $progress->status->value,
                ]
            );
        }

        return $progress;
    }

    public static function markCompleted(Enrollment $enrollment, Activity $activity): self
    {
        // Check if activity was already started before ensuring started state
        $existingProgress = static::query()->where('enrollment_id', $enrollment->id)
            ->where('activity_id', $activity->id)
            ->first();
        
        $wasNeverStarted = $existingProgress === null 
            || $existingProgress->status === ProgressStatus::NotStarted;

        // Ensure started state, emitting activity_started only if never started before
        $progress = static::markStarted($enrollment, $activity, emitEvent: $wasNeverStarted);
        $progress->status = ProgressStatus::Completed;
        $progress->completed_at ??= now();

        $progress->save();

        // Record activity_completed learning event
        LearningEvent::record(
            'activity_completed',
            $enrollment->user_id,
            $enrollment->course_id,
            $activity->id,
            [
                'enrollment_id' => $enrollment->id,
                'progress_status' => $progress->status->value,
                'completed_at' => $progress->completed_at?->toISOString(),
            ]
        );

        return $progress;
    }
}