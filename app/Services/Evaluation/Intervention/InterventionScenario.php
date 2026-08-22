<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Synthetic scenario for validating T04 adaptive intervention selection (M6-03).
 *
 * Reuses the M6-02 EvidenceSpec to drive real T03 inference, whose LearningState
 * is then passed to the real T04 service. Data is synthetic.
 */
final readonly class InterventionScenario implements InterventionEvaluationScenario
{
    /**
     * @param  list<EvidenceSpec>  $evidence
     */
    public function __construct(
        public string $id,
        public string $categoryLabel,
        public string $description,
        public string $concept,
        public ?BloomLevel $bloomDemand,
        public ?DaveLevel $daveDemand,
        public array $evidence,
        public ExpectedIntervention $expected,
    ) {}

    public function scenarioId(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return 'intervention';
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
            'expected' => $this->expected->toArray(),
        ];
    }
}
