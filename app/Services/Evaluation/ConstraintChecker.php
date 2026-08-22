<?php

namespace App\Services\Evaluation;

/**
 * Deterministic constraint and privacy compliance checks for an evaluation
 * outcome (M6-01).
 *
 * Verifies that the observed NEXUS outcome respects the milestone constraints:
 * Bloom/Dave remain task demand only, no ML/LLM decision-making, no longitudinal
 * or reassessment-question generation, and no learner PII leaks into evaluation
 * output. These checks are read-only; they never mutate the outcome.
 */
final class ConstraintChecker
{
    /**
     * Keys/tokens that would indicate leaked personally identifiable information.
     *
     * @var list<string>
     */
    private const PII_TOKENS = ['email', 'e-mail', 'full_name', 'first_name', 'last_name', 'password', 'remember_token', '@'];

    /**
     * @return array{
     *     compliant: bool,
     *     checks: array<string, bool>,
     *     violations: list<string>
     * }
     */
    public function check(ActualOutcome $actual): array
    {
        $provenance = $actual->provenance;
        $violations = [];

        $noMlLlm = ($provenance['ml_or_llm_orchestration'] ?? false) === false
            && ($provenance['ml_decision'] ?? false) === false
            && ($provenance['llm_decision'] ?? false) === false;
        if (! $noMlLlm) {
            $violations[] = 'ML/LLM decision-making detected in NEXUS provenance';
        }

        $noLongitudinal = ($provenance['longitudinal_analysis'] ?? false) === false;
        if (! $noLongitudinal) {
            $violations[] = 'longitudinal analysis detected (out of scope for the evaluated pipeline)';
        }

        $noReassessmentQuestion = ($provenance['creates_reassessment_question'] ?? false) === false;
        if (! $noReassessmentQuestion) {
            $violations[] = 'reassessment question generation detected (out of scope)';
        }

        // Bloom/Dave must be represented as task demand only (a plain level label or
        // null), never as a claimed learner capability/mastery value.
        $bloomDaveTaskDemandOnly = $this->isTaskDemandOnly($actual->bloomDemand)
            && $this->isTaskDemandOnly($actual->daveDemand);
        if (! $bloomDaveTaskDemandOnly) {
            $violations[] = 'Bloom/Dave value is not represented as task-demand-only';
        }

        $privacySafe = $this->isPrivacySafe($actual);
        if (! $privacySafe) {
            $violations[] = 'evaluation output is not privacy-safe (possible PII leak)';
        }

        $checks = [
            'no_ml_or_llm' => $noMlLlm,
            'no_longitudinal_analysis' => $noLongitudinal,
            'no_reassessment_question_generated' => $noReassessmentQuestion,
            'bloom_dave_task_demand_only' => $bloomDaveTaskDemandOnly,
            'privacy_safe' => $privacySafe,
        ];

        return [
            'compliant' => $violations === [],
            'checks' => $checks,
            'violations' => $violations,
        ];
    }

    private function isTaskDemandOnly(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $forbidden = ['mastery', 'mastered', 'capability', 'competent', 'diagnos'];

        foreach ($forbidden as $token) {
            if (str_contains(strtolower($value), $token)) {
                return false;
            }
        }

        return true;
    }

    private function isPrivacySafe(ActualOutcome $actual): bool
    {
        $serialized = strtolower(json_encode($actual->toArray(), JSON_THROW_ON_ERROR));

        foreach (self::PII_TOKENS as $token) {
            if (str_contains($serialized, $token)) {
                return false;
            }
        }

        return true;
    }
}
