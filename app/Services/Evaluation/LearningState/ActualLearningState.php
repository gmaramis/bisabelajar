<?php

namespace App\Services\Evaluation\LearningState;

/**
 * Actual Learning State captured from the M4-T03 inference service for one
 * scenario (M6-02).
 *
 * Captured as detached scalar/array data BEFORE the evaluation transaction is
 * rolled back, so it never persists and never holds live model references.
 * Privacy-safe: the learner appears only as a pseudonymous learner_ref.
 */
final readonly class ActualLearningState
{
    /**
     * @param  list<string>  $behavioralIndicators
     * @param  array<string, mixed>  $fusionSummary
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public string $learnerRef,
        public string $state,
        public string $stateConfidence,
        public ?string $bloomDemand,
        public ?string $daveDemand,
        public ?string $cognitiveIndicator,
        public ?string $psychomotorIndicator,
        public array $behavioralIndicators,
        public string $inferenceRule,
        public string $explanation,
        public array $fusionSummary,
        public bool $idempotent,
        public int $interventionCountAfterInference,
        public array $provenance,
    ) {}

    public function usableCount(): int
    {
        return (int) ($this->fusionSummary['usable_count'] ?? 0);
    }

    public function uncertainCount(): int
    {
        return (int) ($this->fusionSummary['uncertain_count'] ?? 0);
    }

    public function contextDependentCount(): int
    {
        return (int) ($this->fusionSummary['context_dependent_count'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'learner_ref' => $this->learnerRef,
            'state' => $this->state,
            'state_confidence' => $this->stateConfidence,
            'bloom_demand' => $this->bloomDemand,
            'dave_demand' => $this->daveDemand,
            'cognitive_indicator' => $this->cognitiveIndicator,
            'psychomotor_indicator' => $this->psychomotorIndicator,
            'behavioral_indicators' => $this->behavioralIndicators,
            'inference_rule' => $this->inferenceRule,
            'explanation' => $this->explanation,
            'fusion_summary' => $this->fusionSummary,
            'idempotent' => $this->idempotent,
            'intervention_count_after_inference' => $this->interventionCountAfterInference,
            'provenance' => $this->provenance,
        ];
    }
}
