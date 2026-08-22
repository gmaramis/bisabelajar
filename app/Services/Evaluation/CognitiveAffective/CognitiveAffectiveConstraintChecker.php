<?php

namespace App\Services\Evaluation\CognitiveAffective;

/**
 * Deterministic constraint, observability, and privacy checks for the NEXUS
 * cognitive-affective model evaluation (M6-04).
 *
 * Enforces the M6-04 research boundary: every produced indicator must be drawn
 * from the model's OBSERVABLE indicator vocabulary; no psychological/clinical
 * inference may appear in indicators or explanation; Bloom/Dave stay task demand
 * (never reused as a learner-capability indicator); T03 creates no intervention;
 * and no learner PII leaks. Read-only.
 */
final class CognitiveAffectiveConstraintChecker
{
    /**
     * Observable indicator vocabulary supported by the existing model.
     *
     * @var list<string>
     */
    private const OBSERVABLE_COGNITIVE = [
        'corrective_application_observed',
        'successful_task_outcome_observed',
        'unresolved_performance_outcome_observed',
        'task_demand_context_only',
    ];

    /**
     * @var list<string>
     */
    private const OBSERVABLE_PSYCHOMOTOR = [
        'error_correction_then_successful_execution',
        'successful_execution_observed',
        'execution_practice_with_unresolved_outcome',
        'task_skill_demand_context_only',
    ];

    /**
     * @var list<string>
     */
    private const OBSERVABLE_BEHAVIORAL = [
        'persistent_attempt_behavior',
        'corrective_behavior',
        'persistent_engagement',
        'reduced_activity_engagement',
    ];

    /**
     * @var list<string>
     */
    private const PII_TOKENS = ['email', 'e-mail', 'phone', 'ip_address', 'user_agent', 'password', 'remember_token', '@'];

    /**
     * Psychological/clinical vocabulary that must never appear.
     *
     * Deliberately uses adjective/clinical word forms that the model's own
     * responsible disclaimers do NOT use. The T03 explanations legitimately
     * contain negated noun forms ("does not diagnose frustration, confusion",
     * "not psychological diagnosis"); matching those would be a false positive.
     * This mirrors the vocabulary asserted absent by the existing M4-T03 tests.
     */
    private const CLINICAL_PATTERN = '/\b(anxiety|anxious|depression|depressed|demotivated|resilient|frustrated|confused|struggling|clinical|mental[\s-]?health|emotional\s+disorder|mood\s+disorder|personality\s+disorder)\b/i';

    /**
     * @return array{compliant: bool, checks: array<string, bool>, violations: list<string>}
     */
    public function check(ActualIndicators $actual): array
    {
        $violations = [];

        $observable = $this->indicatorsAreObservable($actual);
        if (! $observable) {
            $violations[] = 'a produced indicator is not part of the observable indicator vocabulary';
        }

        $noClinical = $this->noClinicalInference($actual);
        if (! $noClinical) {
            $violations[] = 'psychological/clinical inference vocabulary detected in indicators or explanation';
        }

        $taskDemandOnly = $this->bloomDaveTaskDemandOnly($actual);
        if (! $taskDemandOnly) {
            $violations[] = 'Bloom/Dave task demand leaked into a learner-capability indicator';
        }

        $noIntervention = $actual->interventionCountAfterInference === 0;
        if (! $noIntervention) {
            $violations[] = 'the cognitive-affective inference created an adaptive intervention (belongs to T04)';
        }

        $privacySafe = $this->isPrivacySafe($actual);
        if (! $privacySafe) {
            $violations[] = 'evaluation output is not privacy-safe (possible PII leak)';
        }

        $checks = [
            'indicators_observable' => $observable,
            'no_clinical_inference' => $noClinical,
            'bloom_dave_task_demand_only' => $taskDemandOnly,
            'no_intervention_created' => $noIntervention,
            'privacy_safe' => $privacySafe,
        ];

        return ['compliant' => $violations === [], 'checks' => $checks, 'violations' => $violations];
    }

    private function indicatorsAreObservable(ActualIndicators $actual): bool
    {
        if ($actual->cognitiveIndicator !== null && ! in_array($actual->cognitiveIndicator, self::OBSERVABLE_COGNITIVE, true)) {
            return false;
        }

        if ($actual->psychomotorIndicator !== null && ! in_array($actual->psychomotorIndicator, self::OBSERVABLE_PSYCHOMOTOR, true)) {
            return false;
        }

        foreach ($actual->behavioralIndicators as $indicator) {
            if (! in_array($indicator, self::OBSERVABLE_BEHAVIORAL, true)) {
                return false;
            }
        }

        return true;
    }

    private function noClinicalInference(ActualIndicators $actual): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $actual->cognitiveIndicator,
            $actual->psychomotorIndicator,
            implode(' ', $actual->behavioralIndicators),
            $actual->explanation,
        ])));

        return preg_match(self::CLINICAL_PATTERN, $haystack) !== 1;
    }

    private function bloomDaveTaskDemandOnly(ActualIndicators $actual): bool
    {
        if ($actual->bloomDemand !== null && $actual->cognitiveIndicator === $actual->bloomDemand) {
            return false;
        }

        if ($actual->daveDemand !== null && $actual->psychomotorIndicator === $actual->daveDemand) {
            return false;
        }

        return true;
    }

    private function isPrivacySafe(ActualIndicators $actual): bool
    {
        $serialized = strtolower(json_encode($actual->toArray(), JSON_THROW_ON_ERROR));

        foreach (self::PII_TOKENS as $token) {
            if (str_contains($serialized, $token)) {
                return false;
            }
        }

        if (str_contains($serialized, 'secret learner') || str_contains($serialized, 'sentinel')) {
            return false;
        }

        return true;
    }
}
