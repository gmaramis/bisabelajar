<?php

namespace App\Services\Evaluation\Explainability;

/**
 * Independently authored expectation of the explainability/trustworthiness
 * properties a NEXUS component's output must satisfy (M6-05).
 *
 * INDEPENDENCE CONTRACT: these required properties are authored from the M6-05
 * specification and the component's documented contract. They are never generated
 * by executing the production component under evaluation.
 */
final readonly class ExpectedExplanation
{
    /**
     * @param  list<string>  $explanationMustContain  Substrings the explanation must contain (used for divergence authoring too).
     */
    public function __construct(
        public bool $requireReason = true,
        public bool $requireRule = true,
        public bool $requireProvenance = true,
        public bool $requireTaskDemand = false,
        public bool $requireNoDiagnosis = true,
        public bool $requireNoCausalClaim = true,
        public bool $requireConfidenceVisible = false,
        public array $explanationMustContain = [],
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'require_reason' => $this->requireReason,
            'require_rule' => $this->requireRule,
            'require_provenance' => $this->requireProvenance,
            'require_task_demand' => $this->requireTaskDemand,
            'require_no_diagnosis' => $this->requireNoDiagnosis,
            'require_no_causal_claim' => $this->requireNoCausalClaim,
            'require_confidence_visible' => $this->requireConfidenceVisible,
            'explanation_must_contain' => $this->explanationMustContain,
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
