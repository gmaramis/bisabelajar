<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\WeakAreaClassification;

/**
 * Synthetic scenario for validating M5-04 AI-assisted reassessment (M6-03).
 *
 * When useRealWeakAreaQuery is true, the runner seeds a persistent weak-area
 * learning-state history and invokes the real weak-area query + reassessment
 * service end-to-end. Otherwise it builds a synthetic finding of the given
 * classification and invokes the reassessment service directly. Data is synthetic.
 */
final readonly class ReassessmentScenario implements InterventionEvaluationScenario
{
    public function __construct(
        public string $id,
        public string $categoryLabel,
        public string $description,
        public string $concept,
        public WeakAreaClassification $classification,
        public bool $useRealWeakAreaQuery,
        public ExpectedReassessment $expected,
    ) {}

    public function scenarioId(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return 'reassessment';
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
            'classification' => $this->classification->value,
            'use_real_weak_area_query' => $this->useRealWeakAreaQuery,
            'expected' => $this->expected->toArray(),
        ];
    }
}
