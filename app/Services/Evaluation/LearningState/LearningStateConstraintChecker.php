<?php

namespace App\Services\Evaluation\LearningState;

/**
 * Deterministic constraint and privacy checks specific to M4-T03 Learning State
 * inference (M6-02).
 *
 * Verifies the T03-specific invariants required by the M6-02 spec: T03 creates no
 * adaptive intervention, Bloom/Dave stay task demand (not learner capability), the
 * explanation carries no psychological diagnosis, and no learner PII leaks. All
 * checks are read-only.
 */
final class LearningStateConstraintChecker
{
    /**
     * @var list<string>
     */
    private const PII_TOKENS = ['email', 'e-mail', 'full_name', 'first_name', 'last_name', 'password', 'remember_token', '@'];

    /**
     * Psychological-diagnosis vocabulary that must never appear in a T03 explanation.
     */
    private const DIAGNOSIS_PATTERN = '/\b(frustrated|confused|demotivated|anxious|depressed|resilient|motivated|struggling)\b/i';

    /**
     * @return array{
     *     compliant: bool,
     *     checks: array<string, bool>,
     *     violations: list<string>
     * }
     */
    public function check(ActualLearningState $actual): array
    {
        $violations = [];

        $noIntervention = $actual->interventionCountAfterInference === 0;
        if (! $noIntervention) {
            $violations[] = 'T03 inference created an adaptive intervention (interventions belong to T04)';
        }

        $noDiagnosis = preg_match(self::DIAGNOSIS_PATTERN, $actual->explanation) !== 1;
        if (! $noDiagnosis) {
            $violations[] = 'explanation contains psychological-diagnosis vocabulary';
        }

        $taskDemandOnly = $this->bloomDaveTaskDemandOnly($actual);
        if (! $taskDemandOnly) {
            $violations[] = 'Bloom/Dave task demand leaked into learner capability (indicator equals demand level)';
        }

        $privacySafe = $this->isPrivacySafe($actual);
        if (! $privacySafe) {
            $violations[] = 'evaluation output is not privacy-safe (possible PII leak)';
        }

        $checks = [
            'no_intervention_created_by_t03' => $noIntervention,
            'no_psychological_diagnosis' => $noDiagnosis,
            'bloom_dave_task_demand_only' => $taskDemandOnly,
            'privacy_safe' => $privacySafe,
        ];

        return [
            'compliant' => $violations === [],
            'checks' => $checks,
            'violations' => $violations,
        ];
    }

    private function bloomDaveTaskDemandOnly(ActualLearningState $actual): bool
    {
        // Task demand must not be reused as a claimed learner capability: the
        // cognitive/psychomotor indicators must differ from the raw demand level.
        if ($actual->bloomDemand !== null && $actual->cognitiveIndicator === $actual->bloomDemand) {
            return false;
        }

        if ($actual->daveDemand !== null && $actual->psychomotorIndicator === $actual->daveDemand) {
            return false;
        }

        return true;
    }

    private function isPrivacySafe(ActualLearningState $actual): bool
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
