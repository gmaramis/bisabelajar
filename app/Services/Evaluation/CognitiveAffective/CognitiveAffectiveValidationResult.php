<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Result of validating one cognitive-affective scenario (M6-04).
 *
 * Reuses the shared M6-01 EvaluationStatus and the established result shape.
 */
final readonly class CognitiveAffectiveValidationResult
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
        public string $category,
        public EvaluationStatus $status,
        public ExpectedIndicators $expected,
        public ActualIndicators $actual,
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
            'category' => $this->category,
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
