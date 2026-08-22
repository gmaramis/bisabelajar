<?php

namespace App\Services\Evaluation\Explainability;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Result of validating explainability/trustworthiness for one component scenario
 * (M6-05). Reuses the shared M6-01 EvaluationStatus and the established shape.
 */
final readonly class ExplainabilityValidationResult
{
    /**
     * @param  list<string>  $differences
     * @param  array<string, string>  $dimensions
     * @param  array<string, mixed>  $provenanceCheck
     * @param  array<string, mixed>  $constraintCheck
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $scenarioId,
        public string $component,
        public EvaluationStatus $status,
        public ExpectedExplanation $expected,
        public ActualExplanation $actual,
        public array $differences,
        public array $dimensions,
        public array $provenanceCheck,
        public array $constraintCheck,
        public array $notes,
    ) {}

    public function isPass(): bool
    {
        return $this->status === EvaluationStatus::Pass;
    }

    public function isFail(): bool
    {
        return $this->status === EvaluationStatus::Fail;
    }

    public function isReview(): bool
    {
        return $this->status === EvaluationStatus::Review;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'component' => $this->component,
            'status' => $this->status->value,
            'expected' => $this->expected->toArray(),
            'actual' => $this->actual->toArray(),
            'differences' => $this->differences,
            'dimensions' => $this->dimensions,
            'provenance_check' => $this->provenanceCheck,
            'constraint_check' => $this->constraintCheck,
            'notes' => $this->notes,
        ];
    }
}
