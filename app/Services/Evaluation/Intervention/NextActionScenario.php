<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Synthetic scenario for validating a T05 next-learning-action decision (M6-03).
 *
 * Drives real T03 inference from EvidenceSpec, optionally creates a real T04
 * intervention, then evaluates the real T05 decision. Data is synthetic.
 */
final readonly class NextActionScenario implements InterventionEvaluationScenario
{
    /**
     * @param  list<EvidenceSpec>  $evidence
     * @param  'success'|'failure'|null  $retryOutcome
     */
    public function __construct(
        public string $id,
        public string $categoryLabel,
        public string $description,
        public string $concept,
        public ?BloomLevel $bloomDemand,
        public ?DaveLevel $daveDemand,
        public array $evidence,
        public bool $createIntervention,
        public ?string $retryOutcome,
        public ExpectedNextAction $expected,
    ) {}

    public function scenarioId(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return 'next_action';
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
            'concept' => $this->concept,
            'evidence' => array_map(fn (EvidenceSpec $e): array => $e->toArray(), $this->evidence),
            'create_intervention' => $this->createIntervention,
            'retry_outcome' => $this->retryOutcome,
            'expected' => $this->expected->toArray(),
        ];
    }
}
