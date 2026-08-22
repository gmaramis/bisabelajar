<?php

namespace App\Models;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\WeakAreaClassification;
use Database\Factories\ReassessmentCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Persisted AI-assisted reassessment candidate (M5-04).
 *
 * Candidate only — not learner delivery, not LearningEvent/Evidence/State creation.
 */
#[Fillable([
    'user_id',
    'course_id',
    'candidate_key',
    'research_learner_id',
    'learning_area_key',
    'learning_area_label',
    'learning_area_representation',
    'weak_area_classification',
    'concept',
    'learning_objective',
    'bloom_demand',
    'dave_demand',
    'status',
    'specification',
    'ai_safe_payload',
    'candidate_content',
    'generator_identity',
    'generator_model',
    'generation_metadata',
    'validation_result',
    'validation_errors',
    'failure_reason',
    'generated_at',
    'validated_at',
])]
class ReassessmentCandidate extends Model
{
    /** @use HasFactory<ReassessmentCandidateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReassessmentCandidateStatus::class,
            'weak_area_classification' => WeakAreaClassification::class,
            'bloom_demand' => BloomLevel::class,
            'dave_demand' => DaveLevel::class,
            'specification' => 'array',
            'ai_safe_payload' => 'array',
            'candidate_content' => 'array',
            'generation_metadata' => 'array',
            'validation_result' => 'array',
            'validation_errors' => 'array',
            'generated_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isValidated(): bool
    {
        return $this->status === ReassessmentCandidateStatus::Validated;
    }

    public function deliversToLearner(): bool
    {
        return false;
    }
}
