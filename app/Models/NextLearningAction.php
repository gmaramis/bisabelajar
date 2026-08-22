<?php

namespace App\Models;

use App\Enums\NextLearningActionType;
use Database\Factories\NextLearningActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deterministic next-learning-action decision (M4-T05).
 *
 * Does not create interventions, reassessment questions, or longitudinal analytics.
 */
#[Fillable([
    'user_id',
    'activity_id',
    'learning_state_id',
    'adaptive_intervention_id',
    'decision_key',
    'action',
    'reason',
    'decision_rule',
    'retry_outcome',
    'metadata',
    'decided_at',
])]
class NextLearningAction extends Model
{
    /** @use HasFactory<NextLearningActionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => NextLearningActionType::class,
            'metadata' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function learningState(): BelongsTo
    {
        return $this->belongsTo(LearningState::class);
    }

    public function adaptiveIntervention(): BelongsTo
    {
        return $this->belongsTo(AdaptiveIntervention::class);
    }
}
