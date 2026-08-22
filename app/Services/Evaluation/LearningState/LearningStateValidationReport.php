<?php

namespace App\Services\Evaluation\LearningState;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of Learning State validation results into a report
 * with the M6-02 spec metrics (M6-02).
 *
 * Ordered by scenario_id so identical inputs always produce identical output, and
 * privacy-safe (only pseudonymous learner references appear). Reports evaluation
 * coverage only; it makes no claim about educational effectiveness or causal validity.
 */
final class LearningStateValidationReport
{
    /**
     * @param  list<LearningStateValidationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<LearningStateValidationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (LearningStateValidationResult $a, LearningStateValidationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

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
     * Scenario pass rate, as a fraction in [0, 1].
     */
    public function scenarioPassRate(): float
    {
        $total = count($this->results);

        return $total === 0 ? 0.0 : $this->summary()['pass'] / $total;
    }

    /**
     * Pass rate per authored learning state (from the expected primary state).
     *
     * @return array<string, array{total: int, pass: int}>
     */
    public function stateSpecificPassRate(): array
    {
        $byState = [];

        foreach ($this->results as $result) {
            $state = $result->expected->state->value;
            $byState[$state] ??= ['total' => 0, 'pass' => 0];
            $byState[$state]['total']++;
            if ($result->isPass()) {
                $byState[$state]['pass']++;
            }
        }

        ksort($byState);

        return $byState;
    }

    /**
     * @return array{total: int, pass: int}
     */
    public function boundaryPassRate(): array
    {
        $total = $pass = 0;

        foreach ($this->results as $result) {
            if ($result->category !== 'boundary') {
                continue;
            }
            $total++;
            if ($result->isPass() || $result->isReview()) {
                $pass++;
            }
        }

        return ['total' => $total, 'pass' => $pass];
    }

    public function provenanceCompliance(): bool
    {
        foreach ($this->results as $result) {
            if (($result->provenanceCheck['traceable'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    public function constraintViolationCount(): int
    {
        $count = 0;

        foreach ($this->results as $result) {
            $count += count($result->constraintCheck['violations'] ?? []);
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    public function unresolvedReviewScenarioIds(): array
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
            'generated_by' => 'M6-02 Learning State Validation',
            'summary' => $this->summary(),
            'metrics' => [
                'scenario_pass_rate' => $this->scenarioPassRate(),
                'state_specific_pass_rate' => $this->stateSpecificPassRate(),
                'boundary_pass_rate' => $this->boundaryPassRate(),
                'provenance_compliance' => $this->provenanceCompliance(),
                'constraint_violations' => $this->constraintViolationCount(),
                'unresolved_review_scenarios' => $this->unresolvedReviewScenarioIds(),
            ],
            'scenarios' => array_map(fn (LearningStateValidationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
