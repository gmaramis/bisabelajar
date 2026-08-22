<?php

namespace App\Services\Evaluation\Performance;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of performance/reliability results into an M6-06
 * report (M6-06).
 *
 * Ordered by scenario_id for reproducible output; privacy-safe. Reports objective
 * reliability outcomes and raw measurements (with environment/sample-size context).
 * Distinguishes deterministic technical measurements from any claim of educational
 * effectiveness — no such claim is made.
 */
final class PerformanceEvaluationReport
{
    /**
     * @param  list<PerformanceEvaluationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<PerformanceEvaluationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (PerformanceEvaluationResult $a, PerformanceEvaluationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $sorted;
    }

    /**
     * @return array{total: int, pass: int, fail: int, review: int}
     */
    public function summary(): array
    {
        $pass = $fail = $review = 0;

        foreach ($this->results as $result) {
            match ($result->status) {
                EvaluationStatus::Pass => $pass++,
                EvaluationStatus::Fail => $fail++,
                EvaluationStatus::Review => $review++,
            };
        }

        return ['total' => count($this->results), 'pass' => $pass, 'fail' => $fail, 'review' => $review];
    }

    /**
     * Raw measurements gathered (reported, never thresholded here).
     *
     * @return list<array<string, mixed>>
     */
    public function measurements(): array
    {
        $rows = [];
        foreach ($this->results() as $result) {
            $rows[] = [
                'scenario_id' => $result->scenarioId,
                'operation' => $result->operation,
                'elapsed_ms' => $result->actual->elapsedMs,
                'query_count' => $result->actual->queryCount,
                'memory_delta_kb' => $result->actual->memoryDeltaKb,
                'sample_size' => $result->actual->sampleSize,
            ];
        }

        return $rows;
    }

    public function privacyCompliance(): bool
    {
        foreach ($this->results as $result) {
            if (($result->constraintCheck['compliant'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function reviewScenarioIds(): array
    {
        $ids = [];
        foreach ($this->results() as $result) {
            if ($result->isReview()) {
                $ids[] = $result->scenarioId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'generated_by' => 'M6-06 System & AI Performance Evaluation',
            'environment_note' => 'Measurements are environment-dependent (test database, single process). No performance thresholds are asserted without an evidence-based project baseline.',
            'summary' => $this->summary(),
            'metrics' => [
                'privacy_compliance' => $this->privacyCompliance(),
                'review_scenarios' => $this->reviewScenarioIds(),
                'measurements' => $this->measurements(),
            ],
            'scenarios' => array_map(fn (PerformanceEvaluationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
