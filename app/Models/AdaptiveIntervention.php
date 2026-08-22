<?php

namespace App\Models;

use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\SocraticResponseType;
use Database\Factories\AdaptiveInterventionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adaptive intervention produced from a Learning State (M4-T04).
 *
 * Does not perform Learning Recommendation or longitudinal analytics.
 */
#[Fillable([
    'user_id',
    'activity_id',
    'learning_state_id',
    'intervention_key',
    'intervention_type',
    'socratic_type',
    'target_state',
    'content',
    'reason',
    'selection_rule',
    'is_strong',
    'is_remedial',
    'metadata',
])]
class AdaptiveIntervention extends Model
{
    /** @use HasFactory<AdaptiveInterventionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intervention_type' => InterventionType::class,
            'socratic_type' => SocraticResponseType::class,
            'target_state' => LearningStateValue::class,
            'is_strong' => 'boolean',
            'is_remedial' => 'boolean',
            'metadata' => 'array',
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

    public function nextLearningActions(): HasMany
    {
        return $this->hasMany(NextLearningAction::class);
    }
}
