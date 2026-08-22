<?php

namespace App\Services\Research;

use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Models\AdaptiveIntervention;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;

/**
 * Deterministic multi-factor next-learning-action decision (M4-T05 V1).
 *
 * Does not infer Learning State, create Adaptive Intervention, generate
 * reassessment questions, or perform longitudinal analytics.
 */
final class NextLearningActionService
{
    /**
     * Decide the next learning action for a Learning State.
     *
     * Optional $intervention and $retryOutcome allow explicit multi-factor input.
     * When omitted, the latest remedial intervention and post-intervention evidence
     * are derived from existing records.
     *
     * @param  'success'|'failure'|null  $retryOutcome
     */
    public function decideForLearningState(
        LearningState $learningState,
        ?AdaptiveIntervention $intervention = null,
        ?string $retryOutcome = null,
    ): NextLearningAction {
        $learningState->loadMissing(['activity', 'validatedEvidence.learningEvent']);

        $intervention ??= $this->latestIntervention($learningState);
        $evidence = $learningState->validatedEvidence->sortBy('id')->values();
        $derivedRetry = $retryOutcome ?? $this->deriveRetryOutcome($intervention, $evidence);

        $plan = $this->decide($learningState, $intervention, $derivedRetry, $evidence);
        $decisionKey = $this->decisionKey($learningState, $intervention, $derivedRetry, $plan);

        return NextLearningAction::query()->updateOrCreate(
            ['decision_key' => $decisionKey],
            [
                'user_id' => $learningState->user_id,
                'activity_id' => $learningState->activity_id,
                'learning_state_id' => $learningState->id,
                'adaptive_intervention_id' => $intervention?->id,
                'action' => $plan['action']->value,
                'reason' => $plan['reason'],
                'decision_rule' => $plan['decision_rule'],
                'retry_outcome' => $derivedRetry,
                'metadata' => $plan['metadata'],
                'decided_at' => now(),
            ],
        )->fresh(['learningState', 'adaptiveIntervention', 'activity', 'user']);
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     * @param  'success'|'failure'|null  $retryOutcome
     * @return array{
     *     action: NextLearningActionType,
     *     reason: string,
     *     decision_rule: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function decide(
        LearningState $state,
        ?AdaptiveIntervention $intervention,
        ?string $retryOutcome,
        Collection $evidence,
    ): array {
        $cognitive = $state->cognitive_indicator;
        $psychomotor = $state->psychomotor_indicator;
        $behavioral = is_array($state->behavioral_indicators) ? $state->behavioral_indicators : [];
        $concept = $state->activity?->getConcept() ?? 'the current concept';
        $bloomDemand = $state->bloom_demand?->value ?? 'unknown';
        $daveDemand = $state->dave_demand?->value ?? 'unknown';

        $metadata = [
            'learning_state' => $state->state->value,
            'state_confidence' => $state->state_confidence->value,
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => $psychomotor,
            'behavioral_indicators' => $behavioral,
            'bloom_demand' => $bloomDemand,
            'dave_demand' => $daveDemand,
            'validated_evidence_ids' => $evidence->pluck('id')->values()->all(),
            'adaptive_intervention_id' => $intervention?->id,
            'intervention_type' => $intervention?->intervention_type?->value,
            'retry_outcome' => $retryOutcome,
            'creates_intervention' => false,
            'creates_reassessment_question' => false,
            'ml_decision' => false,
            'llm_decision' => false,
            'longitudinal_analysis' => false,
        ];

        // Priority 1: evidence sufficiency.
        if ($state->state === LearningStateValue::InsufficientEvidence) {
            return $this->plan(
                NextLearningActionType::CollectMoreEvidence,
                'Learning State is insufficient_evidence. Collect more validated learning evidence before a stronger next-step decision.',
                'insufficient_evidence → collect_more_evidence',
                $metadata,
            );
        }

        // Priority 2–4: improvement / stable outcomes.
        if ($state->state === LearningStateValue::Progressing) {
            return $this->plan(
                NextLearningActionType::Continue,
                'Learning State is progressing and the latest validated evidence indicates improvement. Continue to the next learning step.',
                'progressing + improvement → continue',
                $metadata,
            );
        }

        if ($state->state === LearningStateValue::Stable) {
            return $this->plan(
                NextLearningActionType::Continue,
                'Learning State is stable without a new failure pattern. Continue without remedial redirection.',
                'stable + no new failure pattern → continue',
                $metadata,
            );
        }

        // Remaining path: needs_support multi-factor rules.
        if ($state->state === LearningStateValue::NeedsSupport) {
            // Intervention followed by successful retry.
            if ($intervention !== null && $retryOutcome === 'success') {
                return $this->plan(
                    NextLearningActionType::Continue,
                    'Learner was in needs_support, received adaptive intervention, and the latest retry was successful. Continue learning.',
                    'needs_support + intervention + successful retry → continue',
                    $metadata,
                );
            }

            // Persistent weak area after support + failed retry → reassessment decision only.
            if ($intervention !== null
                && $retryOutcome === 'failure'
                && $this->hasPersistentWeakArea($state, $evidence, $behavioral)) {
                return $this->plan(
                    NextLearningActionType::Reassessment,
                    'Learner remains in needs_support with a persistent weak area after intervention and an unsuccessful retry. Reassessment on the same capability is required. No reassessment question is generated by T05.',
                    'needs_support + intervention + failed retry + persistent weak area → reassessment',
                    $metadata + ['reassessment_capability' => $concept],
                );
            }

            // Cognitive unresolved + failed retry → review concept.
            if ($intervention !== null
                && $retryOutcome === 'failure'
                && $this->isCognitiveUnresolved($cognitive)) {
                return $this->plan(
                    NextLearningActionType::ReviewConcept,
                    'Learner remains in needs_support and the latest retry was unsuccessful after cognitive support. Review concept "'.$concept.'" before continuing. Bloom demand '.$bloomDemand.' remains task demand, not demonstrated capability.',
                    'needs_support + cognitive unresolved + retry failure → review_concept',
                    $metadata,
                );
            }

            // Psychomotor unresolved + failed retry → practice / guided retry.
            if ($intervention !== null
                && $retryOutcome === 'failure'
                && $this->isPsychomotorUnresolved($psychomotor)) {
                return $this->plan(
                    NextLearningActionType::GuidedRetry,
                    'Learner remains in needs_support with unresolved psychomotor practice evidence after intervention and an unsuccessful retry. Guided practice again is recommended. Dave demand '.$daveDemand.' remains task demand, not demonstrated skill.',
                    'needs_support + psychomotor unresolved + retry failure → guided_retry',
                    $metadata,
                );
            }

            // Generic failed retry after intervention.
            if ($intervention !== null && $retryOutcome === 'failure') {
                return $this->plan(
                    NextLearningActionType::PracticeAgain,
                    'Learner remains in needs_support and the latest retry was unsuccessful after intervention. Practice the activity again with focus on the observed weak outcome.',
                    'needs_support + intervention + failed retry → practice_again',
                    $metadata,
                );
            }

            // Intervention given, no retry yet → encourage guided retry.
            if ($intervention !== null && $retryOutcome === null) {
                return $this->plan(
                    NextLearningActionType::GuidedRetry,
                    'Learner is in needs_support and an adaptive intervention has been provided. The next step is a guided retry so new validated evidence can be collected.',
                    'needs_support + intervention + no retry yet → guided_retry',
                    $metadata,
                );
            }

            // Needs support without intervention context → practice again (T05 does not create T04).
            return $this->plan(
                NextLearningActionType::PracticeAgain,
                'Learner is in needs_support. Practice the activity again. T05 does not create a new adaptive intervention.',
                'needs_support + no intervention context → practice_again',
                $metadata,
            );
        }

        return $this->plan(
            NextLearningActionType::CollectMoreEvidence,
            'No clearer next-learning-action rule matched. Collect more validated evidence before deciding a stronger action.',
            'fallback → collect_more_evidence',
            $metadata,
        );
    }

    private function latestIntervention(LearningState $state): ?AdaptiveIntervention
    {
        return AdaptiveIntervention::query()
            ->where('user_id', $state->user_id)
            ->where('activity_id', $state->activity_id)
            ->where('is_remedial', true)
            ->orderByDesc('id')
            ->first()
            ?? AdaptiveIntervention::query()
                ->where('learning_state_id', $state->id)
                ->orderByDesc('id')
                ->first();
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     * @return 'success'|'failure'|null
     */
    private function deriveRetryOutcome(?AdaptiveIntervention $intervention, Collection $evidence): ?string
    {
        if ($intervention === null) {
            return null;
        }

        $post = $evidence->filter(
            fn (ValidatedEvidence $item): bool => $item->id > 0
                && (
                    ($item->validated_at !== null && $intervention->created_at !== null && $item->validated_at->greaterThan($intervention->created_at))
                    || $item->created_at?->greaterThan($intervention->created_at)
                    || $item->id > ($intervention->metadata['validated_evidence_ids'][array_key_last($intervention->metadata['validated_evidence_ids'] ?? [])] ?? 0)
                )
        )->values();

        // Prefer evidence not already attached at intervention creation time.
        $priorIds = collect($intervention->metadata['validated_evidence_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $newEvidence = $evidence->filter(
            fn (ValidatedEvidence $item): bool => ! in_array($item->id, $priorIds, true)
        )->values();

        $pool = $newEvidence->isNotEmpty() ? $newEvidence : $post;

        if ($pool->isEmpty()) {
            return null;
        }

        if ($pool->contains(fn (ValidatedEvidence $item): bool => $item->evidence_type === 'submission_accepted')) {
            return 'success';
        }

        if ($pool->contains(fn (ValidatedEvidence $item): bool => in_array($item->evidence_type, [
            'submission_rejected',
            'repeated_submission_failures',
        ], true))) {
            return 'failure';
        }

        return null;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $evidence
     * @param  list<string>  $behavioral
     */
    private function hasPersistentWeakArea(LearningState $state, Collection $evidence, array $behavioral): bool
    {
        $failureTypes = $evidence->whereIn('evidence_type', [
            'submission_rejected',
            'repeated_submission_failures',
        ])->count();

        return $failureTypes >= 2
            && (
                in_array('persistent_attempt_behavior', $behavioral, true)
                || $state->inference_rule === 'needs_support_repeated_failures'
                || $evidence->contains(fn (ValidatedEvidence $item): bool => $item->evidence_type === 'repeated_submission_failures')
            );
    }

    private function isCognitiveUnresolved(?string $cognitive): bool
    {
        return in_array($cognitive, [
            'unresolved_performance_outcome_observed',
            'task_demand_context_only',
        ], true);
    }

    private function isPsychomotorUnresolved(?string $psychomotor): bool
    {
        return in_array($psychomotor, [
            'execution_practice_with_unresolved_outcome',
        ], true) || str_contains((string) $psychomotor, 'unresolved');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     action: NextLearningActionType,
     *     reason: string,
     *     decision_rule: string,
     *     metadata: array<string, mixed>
     * }
     */
    private function plan(
        NextLearningActionType $action,
        string $reason,
        string $decisionRule,
        array $metadata,
    ): array {
        return [
            'action' => $action,
            'reason' => $reason,
            'decision_rule' => $decisionRule,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array{action: NextLearningActionType, decision_rule: string}  $plan
     * @param  'success'|'failure'|null  $retryOutcome
     */
    private function decisionKey(
        LearningState $state,
        ?AdaptiveIntervention $intervention,
        ?string $retryOutcome,
        array $plan,
    ): string {
        $evidenceIds = $state->validatedEvidence->pluck('id')->sort()->values()->implode(',');

        return hash(
            'sha256',
            $state->id.'|'
            .($intervention?->id ?? 'none').'|'
            .($retryOutcome ?? 'none').'|'
            .$plan['action']->value.'|'
            .$plan['decision_rule'].'|'
            .$evidenceIds
        );
    }
}
