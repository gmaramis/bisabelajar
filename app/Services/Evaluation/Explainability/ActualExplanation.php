<?php

namespace App\Services\Evaluation\Explainability;

/**
 * Normalized explanation surface captured from a NEXUS component (M6-05).
 *
 * Captured as detached, privacy-safe scalar/array data BEFORE the evaluation
 * transaction is rolled back. The learner appears only as a pseudonymous
 * learner_ref. Timestamps are intentionally excluded so determinism can be
 * compared on the logical explanation, not on wall-clock fields.
 */
final readonly class ActualExplanation
{
    /**
     * @param  list<int|string>  $provenanceIds
     */
    public function __construct(
        public string $learnerRef,
        public string $component,
        public string $explanationText,
        public ?string $rule,
        public bool $hasProvenance,
        public array $provenanceIds,
        public bool $confidenceVisible,
        public ?string $confidenceValue,
        public bool $bloomDaveTaskDemand,
        public bool $claimsCausal,
        public bool $deterministic,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'learner_ref' => $this->learnerRef,
            'component' => $this->component,
            'explanation_text' => $this->explanationText,
            'rule' => $this->rule,
            'has_provenance' => $this->hasProvenance,
            'provenance_ids' => $this->provenanceIds,
            'confidence_visible' => $this->confidenceVisible,
            'confidence_value' => $this->confidenceValue,
            'bloom_dave_task_demand' => $this->bloomDaveTaskDemand,
            'claims_causal' => $this->claimsCausal,
            'deterministic' => $this->deterministic,
        ];
    }
}
