<?php

namespace App\Services\Evaluation\Explainability;

/**
 * Deterministic explanation-safety, privacy, and constraint checks for M6-05.
 *
 * Enforces the M6-05 trustworthiness boundary on the captured explanation surface:
 * no psychological/clinical diagnosis wording, no unsupported causal claim, and no
 * learner PII. Read-only.
 */
final class ExplainabilityConstraintChecker
{
    /**
     * @var list<string>
     */
    private const PII_TOKENS = ['email', 'e-mail', 'phone', 'ip_address', 'user_agent', 'password', 'remember_token', '@'];

    /**
     * Psychological/clinical vocabulary that must never appear in an explanation.
     *
     * Uses adjective/clinical word forms that the model's own responsible
     * disclaimers do NOT use (they use negated noun forms such as "does not
     * diagnose frustration, confusion" and "not psychological diagnosis"), so
     * these disclaimers are not flagged as false positives.
     */
    private const CLINICAL_PATTERN = '/\b(anxiety|anxious|depression|depressed|demotivated|resilient|frustrated|confused|struggling|clinical|mental[\s-]?health|emotional\s+disorder|mood\s+disorder|personality\s+disorder)\b/i';

    /**
     * @return array{compliant: bool, checks: array<string, bool>, violations: list<string>}
     */
    public function check(ExpectedExplanation $expected, ActualExplanation $actual): array
    {
        $violations = [];

        $noDiagnosis = $this->noDiagnosis($actual);
        if ($expected->requireNoDiagnosis && ! $noDiagnosis) {
            $violations[] = 'explanation contains psychological/clinical diagnosis vocabulary';
        }

        $noCausal = ! $actual->claimsCausal;
        if ($expected->requireNoCausalClaim && ! $noCausal) {
            $violations[] = 'output asserts an unsupported causal/effectiveness claim';
        }

        $privacySafe = $this->isPrivacySafe($actual);
        if (! $privacySafe) {
            $violations[] = 'explanation output is not privacy-safe (possible PII leak)';
        }

        $checks = [
            'no_diagnosis' => $noDiagnosis,
            'no_causal_claim' => $noCausal,
            'privacy_safe' => $privacySafe,
        ];

        return ['compliant' => $violations === [], 'checks' => $checks, 'violations' => $violations];
    }

    private function noDiagnosis(ActualExplanation $actual): bool
    {
        $haystack = strtolower($actual->explanationText.' '.(string) $actual->rule);

        return preg_match(self::CLINICAL_PATTERN, $haystack) !== 1;
    }

    private function isPrivacySafe(ActualExplanation $actual): bool
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
