<?php

namespace App\Services\Evaluation\Performance;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Result of evaluating one performance/reliability scenario (M6-06). Reuses the
 * shared M6-01 EvaluationStatus and the established result shape.
 */
final readonly class PerformanceEvaluationResult
{
    /**
     * @param  list<string>  $differences
     * @param  array<string, string>  $dimensions
     * @param  array<string, mixed>  $constraintCheck
     * @param  list<string>  $notes
     */
    public function __construct(
        public string $scenarioId,
        public string $kind,
        public string $operation,
        public EvaluationStatus $status,
        public ExpectedPerformance $expected,
        public ActualPerformance $actual,
        public array $differences,
        public array $dimensions,
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
            'operation' => $this->operation,
            'status' => $this->status->value,
            'expected' => $this->expected->toArray(),
            'actual' => $this->actual->toArray(),
            'differences' => $this->differences,
            'dimensions' => $this->dimensions,
            'constraint_check' => $this->constraintCheck,
            'notes' => $this->notes,
        ];
    }
}
