<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Services\Evaluation\LearningState\EvidenceSpec;

/**
 * Deterministic, synthetic scenario for validating the NEXUS cognitive-affective
 * model's observable indicators (M6-04).
 *
 * Reuses the M6-02 EvidenceSpec to drive real T03 inference. All data is synthetic;
 * affective information is represented purely as observable learning behavior.
 */
final readonly class CognitiveAffectiveScenario
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
        public ExpectedIndicators $expected,
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
