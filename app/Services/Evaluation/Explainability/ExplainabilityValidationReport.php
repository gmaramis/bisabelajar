<?php

namespace App\Services\Evaluation\Explainability;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of explainability/trustworthiness results (M6-05).
 *
 * Ordered by scenario_id for reproducible output; privacy-safe. Reports structural
 * and logical explainability conformance only — understandability and pedagogical
 * meaningfulness remain expert-review judgments surfaced as REVIEW.
 */
final class ExplainabilityValidationReport
{
    /**
     * @param  list<ExplainabilityValidationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<ExplainabilityValidationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (ExplainabilityValidationResult $a, ExplainabilityValidationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

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

    public function transparencyCompliance(): bool
    {
        foreach ($this->results as $result) {
            if (($result->dimensions['transparency'] ?? 'fail') === 'fail') {
                return false;
            }
        }

        return true;
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
            'generated_by' => 'M6-05 Explainability & Trustworthiness',
            'summary' => $this->summary(),
            'metrics' => [
                'transparency_compliance' => $this->transparencyCompliance(),
                'constraint_compliance' => $this->constraintCompliance(),
                'provenance_compliance' => $this->provenanceCompliance(),
                'review_scenarios' => $this->reviewScenarioIds(),
            ],
            'scenarios' => array_map(fn (ExplainabilityValidationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
