<?php

namespace App\Services\Evaluation\LearningState;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;

/**
 * Deterministic, synthetic Learning State validation scenario (M6-02).
 *
 * Describes an activity blueprint (task demand only) and an ordered set of
 * ValidatedEvidence specifications that drive the M4-T03 inference service, plus
 * the independently authored ExpectedLearningState. Uses synthetic data only.
 */
final readonly class LearningStateScenario
{
    /**
     * @param  list<EvidenceSpec>  $evidence
     */
    public function __construct(
        public string $scenarioId,
        public string $category,
        public string $description,
        public string $concept,
        public ?BloomLevel $bloomDemand,
        public ?DaveLevel $daveDemand,
        public array $evidence,
        public ExpectedLearningState $expected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'category' => $this->category,
            'description' => $this->description,
            'concept' => $this->concept,
            'bloom_demand' => $this->bloomDemand?->value,
            'dave_demand' => $this->daveDemand?->value,
            'evidence' => array_map(fn (EvidenceSpec $e): array => $e->toArray(), $this->evidence),
            'expected' => $this->expected->toArray(),
        ];
    }
}
