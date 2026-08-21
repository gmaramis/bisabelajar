<?php

namespace App\Models;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use Database\Factories\ValidatedEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Contextualized, traceable evidence produced from a raw LearningEvent.
 *
 * Quality and confidence describe evidence usefulness, not a learner state.
 */
#[Fillable([
    'user_id',
    'activity_id',
    'learning_event_id',
    'source_record_type',
    'source_record_id',
    'evidence_category',
    'evidence_type',
    'observed_value',
    'context_summary',
    'quality',
    'confidence',
    'validation_reason',
    'validated_at',
])]
class ValidatedEvidence extends Model
{
    /** @use HasFactory<ValidatedEvidenceFactory> */
    use HasFactory;

    protected $table = 'validated_evidence';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_category' => EvidenceCategory::class,
            'quality' => EvidenceQuality::class,
            'confidence' => EvidenceConfidence::class,
            'observed_value' => 'array',
            'context_summary' => 'array',
            'validated_at' => 'datetime',
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

    public function learningEvent(): BelongsTo
    {
        return $this->belongsTo(LearningEvent::class);
    }
}
