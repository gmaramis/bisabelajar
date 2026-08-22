<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Services\Evaluation\EvaluationStatus;

/**
 * Deterministic aggregation of cognitive-affective validation results (M6-04).
 *
 * Ordered by scenario_id for reproducible output; privacy-safe. Reports observable
 * indicator conformance and constraint compliance only — it makes no claim about
 * construct validity, which requires expert review and later empirical study.
 */
final class CognitiveAffectiveValidationReport
{
    /**
     * @param  list<CognitiveAffectiveValidationResult>  $results
     */
    public function __construct(private readonly array $results) {}

    /**
     * @return list<CognitiveAffectiveValidationResult>
     */
    public function results(): array
    {
        $sorted = $this->results;
        usort($sorted, fn (CognitiveAffectiveValidationResult $a, CognitiveAffectiveValidationResult $b): int => strcmp($a->scenarioId, $b->scenarioId));

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

    public function indicatorObservabilityCompliance(): bool
    {
        foreach ($this->results as $result) {
            if (($result->constraintCheck['checks']['indicators_observable'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }

    public function clinicalInferenceViolationCount(): int
    {
        $count = 0;
        foreach ($this->results as $result) {
            if (($result->constraintCheck['checks']['no_clinical_inference'] ?? true) !== true) {
                $count++;
            }
        }

        return $count;
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
            'generated_by' => 'M6-04 Cognitive-Affective Model Validation',
            'summary' => $this->summary(),
            'metrics' => [
                'indicator_observability_compliance' => $this->indicatorObservabilityCompliance(),
                'clinical_inference_violations' => $this->clinicalInferenceViolationCount(),
                'provenance_compliance' => $this->provenanceCompliance(),
                'review_scenarios' => $this->reviewScenarioIds(),
            ],
            'scenarios' => array_map(fn (CognitiveAffectiveValidationResult $r): array => $r->toArray(), $this->results()),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
