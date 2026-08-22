<?php

namespace App\Services\Research;

use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\SocraticResponseType;
use App\Models\AdaptiveIntervention;
use App\Models\LearningState;

/**
 * Rule-based Adaptive Intervention + Socratic response (M4-T04 V1).
 *
 * Consumes LearningState from M4-T03. Does not infer state, recommend curricula,
 * or perform longitudinal analytics.
 */
final class AdaptiveInterventionService
{
    /**
     * Create or refresh the intervention for a Learning State.
     *
     * Idempotent for the same learning_state_id + selected rule/type.
     */
    public function createForLearningState(LearningState $learningState): AdaptiveIntervention
    {
        $learningState->loadMissing(['activity', 'validatedEvidence.learningEvent']);

        $plan = $this->selectIntervention($learningState);
        $interventionKey = $this->interventionKey($learningState, $plan);

        return AdaptiveIntervention::query()->updateOrCreate(
            ['intervention_key' => $interventionKey],
            [
                'user_id' => $learningState->user_id,
                'activity_id' => $learningState->activity_id,
                'learning_state_id' => $learningState->id,
                'intervention_type' => $plan['type']->value,
                'socratic_type' => $plan['socratic_type']?->value,
                'target_state' => $learningState->state->value,
                'content' => $plan['content'],
                'reason' => $plan['reason'],
                'selection_rule' => $plan['selection_rule'],
                'is_strong' => $plan['is_strong'],
                'is_remedial' => $plan['is_remedial'],
                'metadata' => $plan['metadata'],
            ],
        )->fresh(['learningState', 'activity', 'user']);
    }

    /**
     * @return array{
     *     type: InterventionType,
     *     socratic_type: ?SocraticResponseType,
     *     content: string,
     *     reason: string,
     *     selection_rule: string,
     *     is_strong: bool,
     *     is_remedial: bool,
     *     metadata: array<string, mixed>
     * }
     */
    private function selectIntervention(LearningState $state): array
    {
        $behavioral = is_array($state->behavioral_indicators) ? $state->behavioral_indicators : [];
        $cognitive = $state->cognitive_indicator;
        $psychomotor = $state->psychomotor_indicator;
        $concept = $state->activity?->getConcept() ?? 'the current concept';
        $bloomDemand = $state->bloom_demand?->value ?? 'unknown';
        $daveDemand = $state->dave_demand?->value ?? 'unknown';

        $baseMetadata = [
            'learning_state_id' => $state->id,
            'learning_state' => $state->state->value,
            'state_confidence' => $state->state_confidence->value,
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => $psychomotor,
            'behavioral_indicators' => $behavioral,
            'bloom_demand' => $bloomDemand,
            'dave_demand' => $daveDemand,
            'validated_evidence_ids' => $state->validatedEvidence->pluck('id')->values()->all(),
            'provides_direct_answer' => false,
            'recommendation' => null,
            'longitudinal_analysis' => false,
        ];

        return match ($state->state) {
            LearningStateValue::InsufficientEvidence => $this->plan(
                InterventionType::Hint,
                null,
                'Evidence is not yet sufficient for a strong adaptive intervention. Continue the activity so more validated learning evidence can be collected.',
                'Learning State is insufficient_evidence. No strong remedial intervention is issued from incomplete evidence.',
                'insufficient_evidence_no_strong_intervention',
                false,
                false,
                $baseMetadata + ['support_focus' => 'evidence_collection'],
            ),
            LearningStateValue::Progressing => $this->plan(
                InterventionType::Reinforcement,
                null,
                'You corrected an earlier unsuccessful outcome. Keep applying the same careful approach on the next attempt.',
                'Learning State is progressing based on corrective success in validated evidence. Brief reinforcement is appropriate; remedial intervention is not automatic.',
                'progressing_reinforcement',
                false,
                false,
                $baseMetadata + ['support_focus' => 'reinforcement'],
            ),
            LearningStateValue::Stable => $this->plan(
                InterventionType::Reinforcement,
                null,
                'Your recent outcome looks stable for this activity. Continue with the next step when you are ready.',
                'Learning State is stable. Short reinforcement/feedback is provided without remedial intervention.',
                'stable_reinforcement',
                false,
                false,
                $baseMetadata + ['support_focus' => 'reinforcement'],
            ),
            LearningStateValue::NeedsSupport => $this->planNeedsSupport(
                $state,
                $behavioral,
                $cognitive,
                $psychomotor,
                $concept,
                $bloomDemand,
                $daveDemand,
                $baseMetadata,
            ),
        };
    }

    /**
     * @param  list<string>  $behavioral
     * @param  array<string, mixed>  $baseMetadata
     * @return array{
     *     type: InterventionType,
     *     socratic_type: ?SocraticResponseType,
     *     content: string,
     *     reason: string,
     *     selection_rule: string,
     *     is_strong: bool,
     *     is_remedial: bool,
     *     metadata: array<string, mixed>
     * }
     */
    private function planNeedsSupport(
        LearningState $state,
        array $behavioral,
        ?string $cognitive,
        ?string $psychomotor,
        string $concept,
        string $bloomDemand,
        string $daveDemand,
        array $baseMetadata,
    ): array {
        // Psychomotor practice support: guided retry.
        if (in_array($psychomotor, [
            'execution_practice_with_unresolved_outcome',
        ], true)) {
            return $this->plan(
                InterventionType::GuidedRetry,
                SocraticResponseType::NextStepHint,
                'Try the activity again. Before submitting, re-check each execution step for '.$concept.'. What changed between your previous attempt and this next try?',
                'Learning State is needs_support with psychomotor indicator '.$psychomotor.'. Guided retry supports practice under task demand '.$daveDemand.' without claiming the learner has reached that Dave level.',
                'needs_support_psychomotor_guided_retry',
                true,
                true,
                $baseMetadata + [
                    'support_focus' => 'psychomotor',
                    'socratic_embedded' => true,
                ],
            );
        }

        // Observable reduced engagement → short directed hint.
        if (in_array('reduced_activity_engagement', $behavioral, true)) {
            return $this->plan(
                InterventionType::Hint,
                SocraticResponseType::ClarifyingQuestion,
                'Start with one small step on '.$concept.': what is the first action you will try again?',
                'Learning State is needs_support with reduced_activity_engagement (observable). A short directed response is provided without diagnosing motivation or affect.',
                'needs_support_reduced_engagement_brief_hint',
                true,
                true,
                $baseMetadata + ['support_focus' => 'behavioral_brief'],
            );
        }

        // Persistent attempts → short hint, let learner retry.
        if (in_array('persistent_attempt_behavior', $behavioral, true)) {
            return $this->plan(
                InterventionType::Hint,
                SocraticResponseType::NextStepHint,
                'You have continued attempting the task. Focus on one part of '.$concept.': what condition or step failed in the last unsuccessful outcome?',
                'Learning State is needs_support with persistent_attempt_behavior. A short hint is provided so the learner can retry; no psychological diagnosis is made.',
                'needs_support_persistent_attempt_hint',
                true,
                true,
                $baseMetadata + ['support_focus' => 'cognitive_brief_hint'],
            );
        }

        // Cognitive unresolved outcomes → Socratic question (not a direct answer).
        if ($cognitive === 'unresolved_performance_outcome_observed'
            || $state->inference_rule === 'needs_support_multiple_rejections') {
            return $this->plan(
                InterventionType::SocraticQuestion,
                SocraticResponseType::GuidedQuestion,
                'Look again at '.$concept.'. Which part of your previous attempt produced the unsuccessful outcome, and what would you change first?',
                'Learning State is needs_support with cognitive indicator '.$cognitive.'. A Socratic question is selected instead of giving the answer. Bloom demand '.$bloomDemand.' remains task demand, not demonstrated learner capability.',
                'needs_support_cognitive_socratic',
                true,
                true,
                $baseMetadata + [
                    'support_focus' => 'cognitive',
                    'socratic_embedded' => true,
                ],
            );
        }

        // Repeated failure pattern without acceptance → concept explanation + reflection prompt.
        if ($state->inference_rule === 'needs_support_repeated_failures') {
            return $this->plan(
                InterventionType::ConceptExplanation,
                SocraticResponseType::ConceptCheck,
                'Revisit the core idea behind '.$concept.' using the activity instructions. Then ask yourself: which requirement of the task is still unmet?',
                'Learning State is needs_support from repeated unsuccessful outcomes. A brief concept explanation with a concept-check question is provided without giving the final answer.',
                'needs_support_cognitive_concept_explanation',
                true,
                true,
                $baseMetadata + [
                    'support_focus' => 'cognitive',
                    'socratic_embedded' => true,
                ],
            );
        }

        // Default remedial support.
        return $this->plan(
            InterventionType::CorrectiveFeedback,
            SocraticResponseType::ReflectionQuestion,
            'Review the unsuccessful outcome for '.$concept.'. Identify one concrete change, then try the activity again. What will you verify before the next submission?',
            'Learning State is needs_support based on unresolved unsuccessful outcomes. Corrective feedback with a reflection question supports another attempt without providing a direct answer.',
            'needs_support_corrective_feedback_default',
            true,
            true,
            $baseMetadata + [
                'support_focus' => 'corrective',
                'socratic_embedded' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     type: InterventionType,
     *     socratic_type: ?SocraticResponseType,
     *     content: string,
     *     reason: string,
     *     selection_rule: string,
     *     is_strong: bool,
     *     is_remedial: bool,
     *     metadata: array<string, mixed>
     * }
     */
    private function plan(
        InterventionType $type,
        ?SocraticResponseType $socraticType,
        string $content,
        string $reason,
        string $selectionRule,
        bool $isStrong,
        bool $isRemedial,
        array $metadata,
    ): array {
        return [
            'type' => $type,
            'socratic_type' => $socraticType,
            'content' => $content,
            'reason' => $reason,
            'selection_rule' => $selectionRule,
            'is_strong' => $isStrong,
            'is_remedial' => $isRemedial,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array{
     *     type: InterventionType,
     *     selection_rule: string
     * }  $plan
     */
    private function interventionKey(LearningState $state, array $plan): string
    {
        return hash(
            'sha256',
            $state->id.'|'.$plan['type']->value.'|'.$plan['selection_rule']
        );
    }
}
