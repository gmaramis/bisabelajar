<?php

namespace App\Services\Evaluation\Explainability;

use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Deterministic, synthetic scenario for validating the explainability and
 * trustworthiness of one NEXUS component's output (M6-05).
 *
 * `component` selects which authoritative production component the runner
 * exercises. `evidence` drives the evidence-level components (learning_state,
 * intervention, next_action); other components are seeded by the runner. All data
 * is synthetic.
 */
final readonly class ExplainabilityScenario
{
    /**
     * @param  list<EvidenceSpec>  $evidence
     */
    public function __construct(
        public string $scenarioId,
        public string $component,
        public string $description,
        public string $concept,
        public array $evidence,
        public ExpectedExplanation $expected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'component' => $this->component,
            'description' => $this->description,
            'concept' => $this->concept,
            'evidence' => array_map(fn (EvidenceSpec $e): array => $e->toArray(), $this->evidence),
            'expected' => $this->expected->toArray(),
        ];
    }
}
