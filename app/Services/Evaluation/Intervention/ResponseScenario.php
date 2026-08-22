<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\LearningStateValue;

/**
 * Synthetic scenario for validating M5-05 observed intervention-response
 * classification (M6-03).
 *
 * The runner seeds a before-state, a remedial/non-remedial intervention, and
 * (optionally) an after-state with post evidence, respecting the existing M5
 * temporal ordering (intervention.created_at → subsequent evidence → after state).
 * No delivery timestamp is invented. Data is synthetic.
 */
final readonly class ResponseScenario implements InterventionEvaluationScenario
{
    public function __construct(
        public string $id,
        public string $categoryLabel,
        public string $description,
        public LearningStateValue $beforeState,
        public ?LearningStateValue $afterState,
        public ?string $postEvidenceType,
        public bool $remedial,
        public ?string $afterCognitive,
        public ExpectedResponse $expected,
    ) {}

    public function scenarioId(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return 'response';
    }

    public function category(): string
    {
        return $this->categoryLabel;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->id,
            'kind' => $this->kind(),
            'category' => $this->categoryLabel,
            'description' => $this->description,
            'before_state' => $this->beforeState->value,
            'after_state' => $this->afterState?->value,
            'post_evidence_type' => $this->postEvidenceType,
            'remedial' => $this->remedial,
            'after_cognitive' => $this->afterCognitive,
            'expected' => $this->expected->toArray(),
        ];
    }
}
