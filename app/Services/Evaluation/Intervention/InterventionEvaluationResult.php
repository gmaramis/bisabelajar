<?php

namespace App\Services\Evaluation\Intervention;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Uniform result of evaluating one M6-03 scenario across any of the four kinds
 * (intervention, next_action, reassessment, response).
 *
 * Reuses the shared M6-01 EvaluationStatus and mirrors the established result
 * shape. Expected/actual are captured as privacy-safe arrays so a single result
 * type serves all evaluation kinds.
 */
final readonly class InterventionEvaluationResult
{
    /**
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @param  list<string>  $differences
     * @param  array<string, string>  $dimensions
     * @param  array<string, mixed>  $provenanceCheck
     * @param  array<string, mixed>  $constraintCheck
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $scenarioId,
        public string $kind,
        public string $category,
        public EvaluationStatus $status,
        public array $expected,
        public array $actual,
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
            'kind' => $this->kind,
            'category' => $this->category,
            'status' => $this->status->value,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'differences' => $this->differences,
            'dimensions' => $this->dimensions,
            'provenance_check' => $this->provenanceCheck,
            'constraint_check' => $this->constraintCheck,
            'notes' => $this->notes,
        ];
    }
}
