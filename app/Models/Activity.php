<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic learning activity attached to a Learning Unit.
 *
 * Type-specific engines, submissions, NEXUS, and mastery are out of scope.
 * Configuration is an extensible JSON boundary; type-aware validation is later.
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

    public function isPublished(): bool
    {
        return $this->status === ActivityStatus::Published;
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
        return $this->learningUnit->isPublished()
            && $this->learningUnit->canBePublished();
    }
}
