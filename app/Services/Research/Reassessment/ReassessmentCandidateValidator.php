<?php

namespace App\Services\Research\Reassessment;

/**
 * Deterministic validation of AI-assisted reassessment candidates (M5-04).
 *
 * Independent of the LLM. Invalid candidates must not be treated as valid.
 */
final class ReassessmentCandidateValidator
{
    /**
     * @param  array<string, mixed>  $specification
     * @param  array<string, mixed>  $candidate
     * @return array{valid: bool, errors: list<string>, checks: array<string, bool>}
     */
    public function validate(array $specification, array $candidate): array
    {
        $errors = [];
        $checks = [];

        $required = ['title', 'task_prompt', 'concept', 'bloom_demand', 'task_format'];
        foreach ($required as $field) {
            $ok = isset($candidate[$field]) && is_string($candidate[$field]) && trim($candidate[$field]) !== '';
            $checks['required_'.$field] = $ok;
            if (! $ok) {
                $errors[] = 'missing_or_empty:'.$field;
            }
        }

        $specConcept = (string) ($specification['concept'] ?? '');
        $candConcept = (string) ($candidate['concept'] ?? '');
        $checks['concept_alignment'] = $specConcept !== '' && mb_strtolower($specConcept) === mb_strtolower($candConcept);
        if (! $checks['concept_alignment']) {
            $errors[] = 'concept_misaligned';
        }

        $areaKey = (string) ($specification['learning_area_key'] ?? '');
        $checks['weak_area_alignment'] = $areaKey === '' || str_contains(mb_strtolower($candConcept), mb_strtolower($this->conceptFromAreaKey($areaKey, $specConcept)));
        if ($areaKey !== '' && str_starts_with($areaKey, 'concept:')) {
            $expected = substr($areaKey, strlen('concept:'));
            $checks['weak_area_alignment'] = mb_strtolower($expected) === mb_strtolower($candConcept);
            if (! $checks['weak_area_alignment']) {
                $errors[] = 'weak_area_misaligned';
            }
        }

        $specObjective = $specification['learning_objective'] ?? null;
        if (is_string($specObjective) && trim($specObjective) !== '') {
            $candObjective = (string) ($candidate['learning_objective'] ?? '');
            $checks['learning_objective_alignment'] = mb_strtolower(trim($specObjective)) === mb_strtolower(trim($candObjective));
            if (! $checks['learning_objective_alignment']) {
                $errors[] = 'learning_objective_misaligned';
            }
        } else {
            $checks['learning_objective_alignment'] = true;
        }

        $specBloom = (string) ($specification['bloom_demand'] ?? '');
        $candBloom = (string) ($candidate['bloom_demand'] ?? '');
        $checks['bloom_demand_consistency'] = $specBloom !== '' && $specBloom === $candBloom;
        if (! $checks['bloom_demand_consistency']) {
            $errors[] = 'bloom_demand_mismatch';
        }

        $specDave = $specification['dave_demand'] ?? null;
        $candDave = $candidate['dave_demand'] ?? null;
        if (is_string($specDave) && $specDave !== '') {
            $checks['dave_demand_consistency'] = $specDave === $candDave;
            if (! $checks['dave_demand_consistency']) {
                $errors[] = 'dave_demand_mismatch';
            }
        } else {
            $checks['dave_demand_consistency'] = true;
        }

        $expectedFormat = (string) ($specification['constraints']['task_format'] ?? 'coding_exercise');
        $checks['task_format'] = ($candidate['task_format'] ?? null) === $expectedFormat;
        if (! $checks['task_format']) {
            $errors[] = 'task_format_mismatch';
        }

        $blob = mb_strtolower(implode(' ', array_filter([
            (string) ($candidate['title'] ?? ''),
            (string) ($candidate['task_prompt'] ?? ''),
            (string) ($candidate['scenario'] ?? ''),
            (string) ($candidate['expected_outcome'] ?? ''),
            (string) ($candidate['rubric'] ?? ''),
        ])));

        $checks['no_psychological_claims'] = ! preg_match(
            '/\b(depression|anxiety|personality disorder|psychologically weak|mental illness|iq)\b/i',
            $blob
        );
        if (! $checks['no_psychological_claims']) {
            $errors[] = 'psychological_claim_detected';
        }

        $checks['no_unsupported_competency_claims'] = ! preg_match(
            '/\b(mastered|guaranteed mastery|learner bloom level|learner dave capability)\b/i',
            $blob
        );
        if (! $checks['no_unsupported_competency_claims']) {
            $errors[] = 'unsupported_competency_claim';
        }

        $checks['no_learner_identity_leakage'] = ! preg_match(
            '/\b(email|@|phone|password|ssn)\b/i',
            $blob
        ) && ! isset($candidate['learner_email']) && ! isset($candidate['learner_name']);
        if (! $checks['no_learner_identity_leakage']) {
            $errors[] = 'learner_identity_leakage';
        }

        $includesAnswer = (bool) ($candidate['includes_direct_answer'] ?? false);
        $checks['no_direct_answer'] = $includesAnswer === false
            && ! preg_match('/\b(final answer\s*:|solution code\s*:|answer key\s*:)\b/i', $blob);
        if (! $checks['no_direct_answer']) {
            $errors[] = 'direct_answer_present';
        }

        $checks['provenance_present'] = isset($specification['provenance'])
            && is_array($specification['provenance'])
            && ! empty($specification['provenance']['learning_state_ids'] ?? [])
            && ! empty($specification['provenance']['validated_evidence_ids'] ?? []);
        if (! $checks['provenance_present']) {
            $errors[] = 'provenance_missing';
        }

        $checks['not_empty'] = trim((string) ($candidate['task_prompt'] ?? '')) !== ''
            && trim((string) ($candidate['title'] ?? '')) !== '';
        if (! $checks['not_empty']) {
            $errors[] = 'empty_candidate';
        }

        $sourceTitle = (string) ($specification['source_activity_snapshot']['title'] ?? '');
        $sourceObjective = (string) ($specification['source_activity_snapshot']['learning_objective'] ?? '');
        $checks['not_exact_source_duplicate'] = true;
        if ($sourceTitle !== '' && mb_strtolower(trim((string) ($candidate['title'] ?? ''))) === mb_strtolower(trim($sourceTitle))) {
            $checks['not_exact_source_duplicate'] = false;
            $errors[] = 'exact_source_title_duplicate';
        }
        if ($sourceObjective !== '' && mb_strtolower(trim((string) ($candidate['task_prompt'] ?? ''))) === mb_strtolower(trim($sourceObjective))) {
            $checks['not_exact_source_duplicate'] = false;
            $errors[] = 'exact_source_objective_duplicate';
        }

        $checks['learning_area_not_silently_changed'] = $checks['concept_alignment'];

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'checks' => $checks,
        ];
    }

    private function conceptFromAreaKey(string $areaKey, string $fallback): string
    {
        if (str_starts_with($areaKey, 'concept:')) {
            return substr($areaKey, strlen('concept:'));
        }

        return $fallback;
    }
}
