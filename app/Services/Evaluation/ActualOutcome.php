<?php

namespace App\Services\Evaluation;

/**
 * Actual outcome captured from the NEXUS pipeline for one scenario (M6-01).
 *
 * Captured as detached scalar/array data BEFORE the evaluation transaction is
 * rolled back, so it never holds live production model references and never
 * persists. Privacy-safe: identifies the learner only by a pseudonymous
 * learner_ref (a hash), never by name or email.
 */
final readonly class ActualOutcome
{
    /**
     * @param  list<int>  $validatedEvidenceIds
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public string $learnerRef,
        public string $state,
        public string $stateConfidence,
        public ?string $bloomDemand,
        public ?string $daveDemand,
        public bool $interventionPresent,
        public ?string $interventionType,
        public bool $remedialInterventionCreated,
        public string $nextAction,
        public array $validatedEvidenceIds,
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
            'intervention_present' => $this->interventionPresent,
            'intervention_type' => $this->interventionType,
            'remedial_intervention_created' => $this->remedialInterventionCreated,
            'next_action' => $this->nextAction,
            'validated_evidence_ids' => $this->validatedEvidenceIds,
            'provenance' => $this->provenance,
        ];
    }
}
