<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Support\ActivityConfiguration;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generic learning activity attached to a Learning Unit.
 *
 * Type-specific engines, NEXUS, and mastery are out of scope.
 * Submissions store a generic payload without grading or code execution.
 * Configuration is type-aware JSON with student-safe and tutor-private fields.
 */
#[Fillable([
    'learning_unit_id',
    'title',
    'type',
    'status',
    'sort_order',
    'configuration',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'configuration' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'status' => ActivityStatus::class,
            'sort_order' => 'integer',
            'configuration' => 'array',
        ];
    }

    public function learningUnit(): BelongsTo
    {
        return $this->belongsTo(LearningUnit::class);
    }

    public function activityProgress(): HasMany
    {
        return $this->hasMany(ActivityProgress::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ActivitySubmission::class)->orderBy('attempt_number');
    }

    public function isPublished(): bool
    {
        return $this->status === ActivityStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === ActivityStatus::Archived;
    }

    /**
     * @param  Builder<Activity>  $query
     * @return Builder<Activity>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ActivityStatus::Published);
    }

    public function canBePublished(): bool
    {
        return ! $this->isArchived()
            && $this->learningUnit->isPublished()
            && $this->learningUnit->canBePublished();
    }

    /**
     * Student-facing configuration only. Tutor-private keys are never included.
     *
     * @return array<string, mixed>
     */
    public function studentSafeConfiguration(): array
    {
        return ActivityConfiguration::studentSafe($this->type, $this->configuration);
    }

    /**
     * @return array<string, mixed>
     */
    public function tutorPrivateConfiguration(): array
    {
        $configuration = $this->configuration ?? [];

        return array_filter([
            'tutor' => $configuration['tutor'] ?? [],
            'extensions' => $configuration['extensions'] ?? [],
        ]);
    }

    public function maxAttempts(): int
    {
        $configured = $this->configuration['max_attempts'] ?? 1;

        return max(1, (int) $configured);
    }
}
