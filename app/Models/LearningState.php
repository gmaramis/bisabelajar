<?php

namespace App\Models;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use Database\Factories\LearningStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inferred Learning State from fused ValidatedEvidence (M4-T03).
 *
 * Does not deliver adaptive intervention or recommendations.
 */
#[Fillable([
    'user_id',
    'activity_id',
    'inference_key',
    'state',
    'state_confidence',
    'bloom_demand',
    'dave_demand',
    'cognitive_indicator',
    'psychomotor_indicator',
    'behavioral_indicators',
    'fusion_summary',
    'explanation',
    'inference_rule',
    'inferred_at',
])]
class LearningState extends Model
{
    /** @use HasFactory<LearningStateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => LearningStateValue::class,
            'state_confidence' => StateConfidence::class,
            'bloom_demand' => BloomLevel::class,
            'dave_demand' => DaveLevel::class,
            'behavioral_indicators' => 'array',
            'fusion_summary' => 'array',
            'inferred_at' => 'datetime',
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

    public function validatedEvidence(): BelongsToMany
    {
        return $this->belongsToMany(
            ValidatedEvidence::class,
            'learning_state_evidence',
            'learning_state_id',
            'validated_evidence_id',
        )->withTimestamps();
    }

    public function adaptiveInterventions(): HasMany
    {
        return $this->hasMany(AdaptiveIntervention::class);
    }

    public function nextLearningActions(): HasMany
    {
        return $this->hasMany(NextLearningAction::class);
    }
}
