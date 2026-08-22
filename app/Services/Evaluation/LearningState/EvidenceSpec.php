<?php

namespace App\Services\Evaluation\LearningState;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;

/**
 * Deterministic specification of a single piece of ValidatedEvidence to seed for
 * a Learning State validation scenario (M6-02).
 *
 * This describes T03 inputs (category/quality/confidence) directly so the overlay
 * can validate the inference service across evidence-quality/confidence boundaries.
 * It never sets a learner state — quality/confidence describe evidence usefulness,
 * not a learner outcome. All data is synthetic.
 */
final readonly class EvidenceSpec
{
    public function __construct(
        public string $evidenceType,
        public EvidenceCategory $category,
        public EvidenceQuality $quality,
        public EvidenceConfidence $confidence,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'evidence_type' => $this->evidenceType,
            'evidence_category' => $this->category->value,
            'quality' => $this->quality->value,
            'confidence' => $this->confidence->value,
        ];
    }
}
