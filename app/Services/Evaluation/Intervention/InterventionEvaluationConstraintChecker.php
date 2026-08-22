<?php

namespace App\Services\Evaluation\Intervention;

/**
 * Deterministic constraint and privacy checks for M6-03 evaluation outcomes.
 *
 * Enforces the cross-cutting M6-03 constraints on the captured actual outcome:
 * no learner PII, no direct-answer leakage, no causal-effectiveness claims
 * (observational wording only), and no ML/LLM decision-making. Read-only.
 */
final class InterventionEvaluationConstraintChecker
{
    /**
     * @var list<string>
     */
    private const PII_TOKENS = ['email', 'e-mail', 'phone', 'ip_address', 'user_agent', 'password', 'remember_token', '@'];

    /**
     * @param  array<string, mixed>  $actual
     * @return array{compliant: bool, checks: array<string, bool>, violations: list<string>}
     */
    public function check(string $kind, array $actual): array
    {
        $violations = [];

        $privacySafe = $this->isPrivacySafe($actual);
        if (! $privacySafe) {
            $violations[] = 'evaluation output is not privacy-safe (possible PII leak)';
        }

        $noDirectAnswer = $this->noDirectAnswer($kind, $actual);
        if (! $noDirectAnswer) {
            $violations[] = 'a direct answer was exposed where it is prohibited';
        }

        $noCausalClaim = $this->noCausalClaim($kind, $actual);
        if (! $noCausalClaim) {
            $violations[] = 'a causal-effectiveness claim was present (only observational wording is allowed)';
        }

        $noMlLlm = $this->noMlLlm($actual);
        if (! $noMlLlm) {
            $violations[] = 'ML/LLM was used as a final decision-maker';
        }

        $checks = [
            'privacy_safe' => $privacySafe,
            'no_direct_answer' => $noDirectAnswer,
            'no_causal_claim' => $noCausalClaim,
            'no_ml_or_llm_decision' => $noMlLlm,
        ];

        return [
            'compliant' => $violations === [],
            'checks' => $checks,
            'violations' => $violations,
        ];
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    private function isPrivacySafe(array $actual): bool
    {
        $serialized = strtolower(json_encode($actual, JSON_THROW_ON_ERROR));

        foreach (self::PII_TOKENS as $token) {
            if (str_contains($serialized, $token)) {
                return false;
            }
        }

        // A learner name/email must never be copied into evaluation output. The
        // runner deliberately seeds a sentinel identity to make leaks detectable.
        if (str_contains($serialized, 'secret learner') || str_contains($serialized, 'sentinel')) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    private function noDirectAnswer(string $kind, array $actual): bool
    {
        if ($kind === 'intervention') {
            return ($actual['provides_direct_answer'] ?? false) === false;
        }

        if ($kind === 'reassessment') {
            // includes_direct_answer is null when no candidate was generated.
            $includes = $actual['includes_direct_answer'] ?? false;

            return $includes === false || $includes === null;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    private function noCausalClaim(string $kind, array $actual): bool
    {
        if ($kind !== 'response') {
            return true;
        }

        return ($actual['claims_causal_effectiveness'] ?? false) === false
            && ($actual['claims_intervention_caused_improvement'] ?? false) === false
            && ($actual['claims_treatment_effect'] ?? false) === false;
    }

    /**
     * @param  array<string, mixed>  $actual
     */
    private function noMlLlm(array $actual): bool
    {
        return ($actual['ml_or_llm_decision_maker'] ?? false) === false;
    }
}
