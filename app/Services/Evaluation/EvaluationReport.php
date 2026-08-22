<?php

namespace App\Services\Evaluation;

/**
 * Deterministic aggregation of scenario results into an evaluation report (M6-01).
 *
 * The report is an evaluation-only artifact. It is ordered by scenario_id so the
 * same set of results always produces byte-identical output, and it contains no
 * learner PII (only pseudonymous learner references from ActualOutcome).
 */
final class EvaluationReport
{
    /**
     * @param  list<ScenarioResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<ScenarioResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (ScenarioResult $a, ScenarioResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $sorted;
    }

    /**
     * @return array{total: int, pass: int, fail: int, review: int}
     */
    public function summary(): array
    {
        $pass = 0;
        $fail = 0;
        $review = 0;

        foreach ($this->results as $result) {
            match ($result->status) {
                EvaluationStatus::Pass => $pass++,
                EvaluationStatus::Fail => $fail++,
                EvaluationStatus::Review => $review++,
            };
        }

        return [
            'total' => count($this->results),
            'pass' => $pass,
            'fail' => $fail,
            'review' => $review,
        ];
    }

    /**
     * @return array{
     *     generated_by: string,
     *     summary: array{total: int, pass: int, fail: int, review: int},
     *     scenarios: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'generated_by' => 'M6-01 NEXUS Evaluation Framework',
            'summary' => $this->summary(),
            'scenarios' => array_map(
                fn (ScenarioResult $r): array => $r->toArray(),
                $this->results(),
            ),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
