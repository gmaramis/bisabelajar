<?php

namespace App\Services\Research\Reassessment;

use App\Enums\WeakAreaClassification;

/**
 * Builds a deterministic reassessment specification from an M5-03 weak-area finding.
 *
 * The specification is the authoritative research requirement for candidate generation.
 */
final class ReassessmentSpecificationBuilder
{
    /**
     * @param  array<string, mixed>  $finding  M5-03 finding
     * @param  array<string, mixed>|null  $sourceActivitySnapshot
     * @return array<string, mixed>
     */
    public function build(array $finding, ?array $sourceActivitySnapshot = null): array
    {
        $classification = (string) ($finding['classification'] ?? '');
        $concept = $this->resolveConcept($finding, $sourceActivitySnapshot);
        $bloom = $this->resolveBloom($finding, $sourceActivitySnapshot);
        $dave = $this->resolveDave($finding, $sourceActivitySnapshot);
        $objective = $sourceActivitySnapshot['learning_objective']
            ?? $finding['learning_objective']
            ?? null;

        return [
            'research_learner_id' => $finding['research_learner_id'] ?? null,
            'learner_id' => $finding['learner_id'] ?? null,
            'course_id' => $finding['course_id'] ?? null,
            'learning_area_key' => $finding['learning_area_key'] ?? null,
            'learning_area_label' => $finding['learning_area_label'] ?? null,
            'learning_area_representation' => $finding['learning_area_representation'] ?? 'activity_concept',
            'weak_area_classification' => $classification,
            'concept' => $concept,
            'learning_objective' => is_string($objective) ? $objective : null,
            'source_activity_ids' => array_values($finding['activity_ids'] ?? []),
            'supporting_evidence_ids' => array_values($finding['supporting_evidence_ids'] ?? []),
            'supporting_learning_state_ids' => array_values($finding['supporting_learning_state_ids'] ?? []),
            'bloom_demand' => $bloom,
            'dave_demand' => $dave,
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'learner_capability_semantics' => 'not_inferred',
            'reason_for_reassessment' => $finding['explanation'] ?? 'Weak area requires competency-aligned reassessment candidate.',
            'detection_rule' => $finding['detection_rule'] ?? null,
            'evidence_quality_summary' => $finding['evidence_quality_summary'] ?? [],
            'evidence_confidence_summary' => $finding['evidence_confidence_summary'] ?? [],
            'trajectory_sequence' => $finding['trajectory']['sequence'] ?? [],
            'source_activity_snapshot' => $sourceActivitySnapshot,
            'constraints' => [
                'must_target_learning_area_key' => $finding['learning_area_key'] ?? null,
                'must_preserve_concept' => $concept,
                'must_preserve_bloom_demand' => $bloom,
                'must_preserve_dave_demand' => $dave,
                'must_preserve_learning_objective' => is_string($objective) ? $objective : null,
                'task_format' => 'coding_exercise',
                'must_differ_from_source_activity' => true,
                'must_not_include_direct_answer' => true,
                'must_not_include_learner_pii' => true,
                'must_not_claim_psychological_state' => true,
                'must_not_claim_learner_capability_from_bloom_dave' => true,
                'llm_may_not_decide_eligibility' => true,
            ],
            'provenance' => [
                'weak_area_classification' => $classification,
                'learning_state_ids' => array_values($finding['supporting_learning_state_ids'] ?? []),
                'validated_evidence_ids' => array_values($finding['supporting_evidence_ids'] ?? []),
                'activity_ids' => array_values($finding['activity_ids'] ?? []),
            ],
            'eligible_classifications' => [
                WeakAreaClassification::WeakPersistent->value,
                WeakAreaClassification::WeakRepeatedFailure->value,
                WeakAreaClassification::WeakUnresolved->value,
            ],
        ];
    }

    /**
     * Privacy-safe payload for AI generators (no email/name/phone).
     *
     * @param  array<string, mixed>  $specification
     * @return array<string, mixed>
     */
    public function aiSafePayload(array $specification): array
    {
        return [
            'research_learner_id' => $specification['research_learner_id'] ?? null,
            'learning_area_key' => $specification['learning_area_key'] ?? null,
            'learning_area_label' => $specification['learning_area_label'] ?? null,
            'concept' => $specification['concept'] ?? null,
            'learning_objective' => $specification['learning_objective'] ?? null,
            'bloom_demand' => $specification['bloom_demand'] ?? null,
            'dave_demand' => $specification['dave_demand'] ?? null,
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'task_format' => $specification['constraints']['task_format'] ?? 'coding_exercise',
            'reason_for_reassessment' => $specification['reason_for_reassessment'] ?? null,
            'constraints' => $specification['constraints'] ?? [],
            // Explicitly omit learner_id, email, name, phone.
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  array<string, mixed>|null  $snapshot
     */
    private function resolveConcept(array $finding, ?array $snapshot): ?string
    {
        if (is_string($snapshot['concept'] ?? null) && $snapshot['concept'] !== '') {
            return $snapshot['concept'];
        }

        $label = $finding['learning_area_label'] ?? null;
        if (is_string($label) && $label !== '') {
            return $label;
        }

        $key = (string) ($finding['learning_area_key'] ?? '');
        if (str_starts_with($key, 'concept:')) {
            return substr($key, strlen('concept:'));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  array<string, mixed>|null  $snapshot
     */
    private function resolveBloom(array $finding, ?array $snapshot): ?string
    {
        if (is_string($snapshot['bloom_demand'] ?? null)) {
            return $snapshot['bloom_demand'];
        }

        $context = $finding['bloom_demand_context'] ?? [];

        return is_array($context) && isset($context[0]) ? (string) $context[0] : null;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @param  array<string, mixed>|null  $snapshot
     */
    private function resolveDave(array $finding, ?array $snapshot): ?string
    {
        if (is_array($snapshot) && array_key_exists('dave_demand', $snapshot)) {
            return is_string($snapshot['dave_demand']) ? $snapshot['dave_demand'] : null;
        }

        $context = $finding['dave_demand_context'] ?? [];

        return is_array($context) && isset($context[0]) ? (string) $context[0] : null;
    }
}
