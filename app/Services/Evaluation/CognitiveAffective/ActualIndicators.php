<?php

namespace App\Services\Evaluation\CognitiveAffective;

/**
 * Actual observable indicators captured from the M4-T03 model for one scenario
 * (M6-04).
 *
 * Captured as detached scalar/array data BEFORE the evaluation transaction is
 * rolled back. Privacy-safe: the learner appears only as a pseudonymous
 * learner_ref. Indicators are observable behavior labels only.
 */
final readonly class ActualIndicators
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
        public string $explanation,
        public array $fusionSummary,
        public bool $deterministic,
        public int $interventionCountAfterInference,
        public array $provenance,
    ) {}

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
            'explanation' => $this->explanation,
            'fusion_summary' => $this->fusionSummary,
            'deterministic' => $this->deterministic,
            'intervention_count_after_inference' => $this->interventionCountAfterInference,
            'provenance' => $this->provenance,
        ];
    }
}
