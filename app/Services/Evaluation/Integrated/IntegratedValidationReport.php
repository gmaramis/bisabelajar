<?php

namespace App\Services\Evaluation\Integrated;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of integrated end-to-end validation results (M6-07).
 *
 * Ordered by scenario_id for reproducible output; privacy-safe. Reports integrated
 * cross-layer conformance only. This is not a real-learner pilot, an educational
 * effectiveness study, or a deployment approval.
 */
final class IntegratedValidationReport
{
    /**
     * @param  list<IntegratedValidationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<IntegratedValidationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (IntegratedValidationResult $a, IntegratedValidationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

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

    public function crossLayerConsistency(): bool
    {
        foreach ($this->results as $result) {
            foreach (['evidence_state_linkage', 'next_action_linkage', 'provenance_completeness', 'task_demand_consistency'] as $dim) {
                if (($result->dimensions[$dim] ?? 'pass') === 'fail' && ! $result->isFail()) {
                    return false;
                }
                if (($result->dimensions[$dim] ?? 'pass') === 'fail' && $result->path !== 'divergence') {
                    // A cross-layer invariant failure outside the intentional divergence self-test is a real problem.
                    return false;
                }
            }
        }

        return true;
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
     * @return list<string>
     */
    public function blockingFailureScenarioIds(): array
    {
        $ids = [];
        foreach ($this->results() as $result) {
            if ($result->isFail()) {
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
            'generated_by' => 'M6-07 Integrated NEXUS Validation',
            'boundary_note' => 'Validates the integrated system only; not a real-learner pilot, effectiveness study, or deployment approval.',
            'summary' => $this->summary(),
            'metrics' => [
                'cross_layer_consistency' => $this->crossLayerConsistency(),
                'privacy_compliance' => $this->privacyCompliance(),
                'review_scenarios' => $this->reviewScenarioIds(),
                'blocking_failures' => $this->blockingFailureScenarioIds(),
            ],
            'scenarios' => array_map(fn (IntegratedValidationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
