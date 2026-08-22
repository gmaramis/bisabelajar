<?php

namespace App\Services\Evaluation\Integrated;

/**
 * Actual end-to-end integrated outcome captured from the real NEXUS pipeline
 * (M6-07).
 *
 * Captured as detached, privacy-safe scalar/array data BEFORE the evaluation
 * transaction is rolled back. Holds the terminal chain outputs plus the computed
 * cross-layer consistency booleans that make integration validation possible.
 * The learner appears only as a pseudonymous learner_ref.
 */
final readonly class ActualIntegration
{
    /**
     * @param  array<string, mixed>  $provenance
     * @param  array<string, mixed>  $reassessment
     * @param  array<string, mixed>  $contextualVariation
     */
    public function __construct(
        public string $learnerRef,
        public string $path,
        public string $terminalState,
        public string $terminalNextAction,
        public bool $interventionPresent,
        public bool $retryRan,
        public ?bool $retryConsumesSameIntervention,
        public bool $evidenceMatchesState,
        public ?bool $interventionLinksState,
        public bool $nextActionLinksState,
        public bool $provenanceComplete,
        public bool $taskDemandConsistent,
        public bool $claimsCausal,
        public bool $deterministic,
        public array $provenance,
        public array $reassessment,
        public array $contextualVariation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'learner_ref' => $this->learnerRef,
            'path' => $this->path,
            'terminal_state' => $this->terminalState,
            'terminal_next_action' => $this->terminalNextAction,
            'intervention_present' => $this->interventionPresent,
            'retry_ran' => $this->retryRan,
            'retry_consumes_same_intervention' => $this->retryConsumesSameIntervention,
            'evidence_matches_state' => $this->evidenceMatchesState,
            'intervention_links_state' => $this->interventionLinksState,
            'next_action_links_state' => $this->nextActionLinksState,
            'provenance_complete' => $this->provenanceComplete,
            'task_demand_consistent' => $this->taskDemandConsistent,
            'claims_causal' => $this->claimsCausal,
            'deterministic' => $this->deterministic,
            'provenance' => $this->provenance,
            'reassessment' => $this->reassessment,
            'contextual_variation' => $this->contextualVariation,
        ];
    }
}
