<?php

namespace App\Services\Evaluation\Intervention;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of M6-03 evaluation results into a report with the
 * spec metrics (M6-03).
 *
 * Ordered by scenario_id so identical inputs produce identical output; privacy-safe
 * (only pseudonymous references appear). Reports observed structural/constraint
 * conformance only — never intervention effectiveness or learner improvement.
 */
final class InterventionEvaluationReport
{
    /**
     * @param  list<InterventionEvaluationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<InterventionEvaluationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (InterventionEvaluationResult $a, InterventionEvaluationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

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
     * @return array{intervention: array{total: int, pass: int}, reassessment: array{total: int, pass: int}}
     */
    public function passRatesByKind(): array
    {
        $tally = [
            'intervention' => ['total' => 0, 'pass' => 0],
            'reassessment' => ['total' => 0, 'pass' => 0],
        ];

        foreach ($this->results as $result) {
            if ($result->kind === 'intervention' || $result->kind === 'next_action') {
                $tally['intervention']['total']++;
                if ($result->isPass()) {
                    $tally['intervention']['pass']++;
                }
            }
            if ($result->kind === 'reassessment') {
                $tally['reassessment']['total']++;
                if ($result->isPass()) {
                    $tally['reassessment']['pass']++;
                }
            }
        }

        return $tally;
    }

    public function constraintCompliance(): bool
    {
        foreach ($this->results as $result) {
            if (($result->constraintCheck['compliant'] ?? false) !== true) {
                return false;
            }
        }

        return true;
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
            'generated_by' => 'M6-03 Intervention & Reassessment Evaluation',
            'summary' => $this->summary(),
            'metrics' => [
                'pass_rates_by_kind' => $this->passRatesByKind(),
                'constraint_compliance' => $this->constraintCompliance(),
                'provenance_compliance' => $this->provenanceCompliance(),
                'review_scenarios' => $this->reviewScenarioIds(),
            ],
            'scenarios' => array_map(fn (InterventionEvaluationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
