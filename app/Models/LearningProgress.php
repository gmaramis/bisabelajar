<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Database\Factories\LearningProgressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Basic learning progress for a student Learning Unit.
 *
 * Completed is not mastered. Mastery, competency, and NEXUS are out of M1.
 */
#[Fillable([
    'enrollment_id',
    'user_id',
    'learning_unit_id',
    'status',
    'started_at',
    'completed_at',
])]
class LearningProgress extends Model
{
    /** @use HasFactory<LearningProgressFactory> */
    use HasFactory;

    protected $table = 'learning_progress';

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

    public function learningUnit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ProgressStatus::Completed;
    }

    public static function statusFor(?self $progress): ProgressStatus
    {
        return $progress?->status ?? ProgressStatus::NotStarted;
    }

    public static function markInProgress(Enrollment $enrollment, LearningUnit $learningUnit): self
    {
        $progress = static::query()->firstOrNew([
            'enrollment_id' => $enrollment->id,
            'learning_unit_id' => $learningUnit->id,
        ]);

        $progress->user_id = $enrollment->user_id;

        if ($progress->status !== ProgressStatus::Completed) {
            $progress->status = ProgressStatus::InProgress;
            $progress->started_at ??= now();
        }

        $progress->save();

        return $progress;
    }

    public static function markCompleted(Enrollment $enrollment, LearningUnit $learningUnit): self
    {
        $progress = static::markInProgress($enrollment, $learningUnit);
        $progress->status = ProgressStatus::Completed;
        $progress->completed_at ??= now();
        $progress->save();

        return $progress;
    }
}
