<?php

namespace App\Services\Research;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionResponseClassification;
use App\Enums\LearningStateTransitionType;
use App\Enums\LearningStateValue;
use App\Enums\ObservedImprovementSignal;
use App\Models\AdaptiveIntervention;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Deterministic intervention-response and observed-improvement analysis (M5-05).
 *
 * Derived/read-only. Reuses M4 interventions + M5-01/02 evidence and trajectory.
 * Does not claim causal effectiveness, treatment effects, or statistical significance.
 */
final class InterventionResponseQuery
{
    private const UNRESOLVED_COGNITIVE = [
        'unresolved_performance_outcome_observed',
    ];

    private const UNRESOLVED_PSYCHOMOTOR = [
        'execution_practice_with_unresolved_outcome',
    ];

    private const FAILURE_TYPES = [
        'submission_rejected',
        'repeated_submission_failures',
    ];

    private const SUCCESS_TYPES = [
        'submission_accepted',
    ];

    public function __construct(
        private readonly ResearchEvidenceQuery $researchEvidence,
        private readonly LearningStateTrajectoryQuery $trajectoryQuery,
    ) {}

    /**
     * Analyze response after one AdaptiveIntervention.
     *
     * @return array<string, mixed>
     */
    public function forIntervention(AdaptiveIntervention $intervention): array
    {
        $intervention->loadMissing([
            'learningState.validatedEvidence.learningEvent',
            'activity.learningUnit.module.course',
            'nextLearningActions',
            'user',
        ]);

        $beforeState = $intervention->learningState;
        $cutAt = $intervention->created_at;

        $afterStates = LearningState::query()
            ->with(['validatedEvidence.learningEvent'])
            ->where('user_id', $intervention->user_id)
            ->where('activity_id', $intervention->activity_id)
            ->where(function ($query) use ($intervention, $cutAt): void {
                $query->where('inferred_at', '>', $cutAt)
                    ->orWhere(function ($inner) use ($intervention, $cutAt): void {
                        $inner->where('inferred_at', '=', $cutAt)
                            ->where('id', '>', $intervention->learning_state_id);
                    });
            })
            ->orderBy('inferred_at')
            ->orderBy('id')
            ->get();

        /** @var LearningState|null $afterState */
        $afterState = $afterStates->last();

        $allEvidence = ValidatedEvidence::query()
            ->with('learningEvent')
            ->where('user_id', $intervention->user_id)
            ->where('activity_id', $intervention->activity_id)
            ->orderBy('id')
            ->get();

        [$preEvidence, $postEvidence] = $this->splitEvidence($intervention, $allEvidence);

        $linkedActions = NextLearningAction::query()
            ->where('adaptive_intervention_id', $intervention->id)
            ->orderBy('decided_at')
            ->orderBy('id')
            ->get();

        $retryOutcomes = $linkedActions
            ->pluck('retry_outcome')
            ->filter()
            ->values()
            ->all();

        $transitionType = null;
        if ($beforeState !== null && $afterState !== null) {
            $transitionType = $this->trajectoryQuery->classifyTransition(
                $beforeState->state,
                $afterState->state,
            );
        }

        [$response, $improvement, $rule, $explanation] = $this->classify(
            $beforeState,
            $afterState,
            $transitionType,
            $preEvidence,
            $postEvidence,
            $retryOutcomes,
        );

        $concept = $intervention->activity?->getConcept();

        return [
            'research_learner_id' => $this->researchEvidence->researchLearnerId((int) $intervention->user_id),
            'learner_id' => $intervention->user_id,
            'intervention_context' => [
                'adaptive_intervention_id' => $intervention->id,
                'intervention_type' => $this->enumValue($intervention->intervention_type),
                'selection_rule' => $intervention->selection_rule,
                'is_remedial' => (bool) $intervention->is_remedial,
                'learning_state_id' => $intervention->learning_state_id,
                'activity_id' => $intervention->activity_id,
                'available_at' => optional($intervention->created_at)?->toIso8601String(),
                'timestamp_semantics' => 'adaptive_interventions.created_at is used as the availability/cut timestamp because a separate delivery timestamp is not stored.',
            ],
            'learning_area' => [
                'representation' => $concept ? 'activity_concept' : 'activity',
                'key' => $concept ? 'concept:'.mb_strtolower($concept) : 'activity:'.$intervention->activity_id,
                'label' => $concept ?? $intervention->activity?->title,
                'bloom_demand' => $this->enumValue($beforeState?->bloom_demand ?? $intervention->activity?->getBloomDemand()),
                'dave_demand' => $this->enumValue($beforeState?->dave_demand ?? $intervention->activity?->getDaveDemand()),
                'bloom_semantics' => 'task_demand',
                'dave_semantics' => 'task_demand',
            ],
            'observed_outcome' => [
                'before_learning_state_id' => $beforeState?->id,
                'before_state' => $this->enumValue($beforeState?->state),
                'after_learning_state_id' => $afterState?->id,
                'after_state' => $this->enumValue($afterState?->state),
                'state_transition_type' => $transitionType?->value,
                'pre_evidence_ids' => $preEvidence->pluck('id')->values()->all(),
                'post_evidence_ids' => $postEvidence->pluck('id')->values()->all(),
                'post_acceptance_count' => $postEvidence->whereIn('evidence_type', self::SUCCESS_TYPES)->count(),
                'post_failure_count' => $postEvidence->whereIn('evidence_type', self::FAILURE_TYPES)->count(),
                'retry_outcomes' => $retryOutcomes,
                'linked_next_learning_action_ids' => $linkedActions->pluck('id')->values()->all(),
            ],
            'research_interpretation' => [
                'response_classification' => $response->value,
                'observed_improvement_signal' => $improvement->value,
                'comparison_rule' => $rule,
                'explanation' => $explanation,
                'observed_improvement' => in_array($improvement, [
                    ObservedImprovementSignal::ObservedImprovement,
                    ObservedImprovementSignal::StabilizationSignal,
                ], true),
                'claims_causal_effectiveness' => false,
                'claims_treatment_effect' => false,
                'claims_intervention_caused_improvement' => false,
            ],
            'confidence' => $this->confidence($beforeState, $afterState, $postEvidence),
            'provenance' => [
                'adaptive_intervention_id' => $intervention->id,
                'before_learning_state_id' => $beforeState?->id,
                'after_learning_state_id' => $afterState?->id,
                'pre_validated_evidence_ids' => $preEvidence->pluck('id')->values()->all(),
                'post_validated_evidence_ids' => $postEvidence->pluck('id')->values()->all(),
                'pre_learning_event_ids' => $preEvidence->pluck('learning_event_id')->filter()->unique()->values()->all(),
                'post_learning_event_ids' => $postEvidence->pluck('learning_event_id')->filter()->unique()->values()->all(),
            ],
            'temporal_window' => [
                'intervention_available_at' => optional($cutAt)?->toIso8601String(),
                'after_state_inferred_at' => optional($afterState?->inferred_at)?->toIso8601String(),
                'ordering' => 'intervention.created_at → subsequent LearningEvent.occurred_at / ValidatedEvidence.validated_at → LearningState.inferred_at',
                'delivery_timestamp_available' => false,
            ],
            'analyzed_at' => now()->toIso8601String(),
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * Analyze all interventions for a learner on one activity (chronological).
     *
     * @return array<string, mixed>
     */
    public function forLearnerActivity(int $userId, int $activityId): array
    {
        $interventions = AdaptiveIntervention::query()
            ->with(['learningState', 'activity', 'nextLearningActions'])
            ->where('user_id', $userId)
            ->where('activity_id', $activityId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $analyses = $interventions
            ->map(fn (AdaptiveIntervention $intervention): array => $this->forIntervention($intervention))
            ->values()
            ->all();

        return [
            'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
            'learner_id' => $userId,
            'activity_id' => $activityId,
            'analyses' => $analyses,
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $allEvidence
     * @return array{0: Collection<int, ValidatedEvidence>, 1: Collection<int, ValidatedEvidence>}
     */
    private function splitEvidence(AdaptiveIntervention $intervention, Collection $allEvidence): array
    {
        $priorIds = collect($intervention->metadata['validated_evidence_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->all();

        $cutAt = $intervention->created_at;

        $pre = $allEvidence->filter(function (ValidatedEvidence $evidence) use ($priorIds, $cutAt): bool {
            if (in_array($evidence->id, $priorIds, true)) {
                return true;
            }

            $at = $evidence->learningEvent?->occurred_at ?? $evidence->validated_at ?? $evidence->created_at;

            return $cutAt !== null && $at !== null && $at->lte($cutAt);
        })->values();

        $post = $allEvidence->filter(function (ValidatedEvidence $evidence) use ($priorIds, $cutAt): bool {
            if (in_array($evidence->id, $priorIds, true)) {
                return false;
            }

            $at = $evidence->learningEvent?->occurred_at ?? $evidence->validated_at ?? $evidence->created_at;

            if ($cutAt !== null && $at !== null && $at->gt($cutAt)) {
                return true;
            }

            // Fallback: newer evidence IDs than those known at intervention time.
            if ($priorIds !== [] && $evidence->id > max($priorIds)) {
                return true;
            }

            return false;
        })->filter(fn (ValidatedEvidence $evidence): bool => $this->isUsable($evidence))->values();

        $preUsable = $pre->filter(fn (ValidatedEvidence $evidence): bool => $this->isUsable($evidence))->values();

        return [$preUsable, $post];
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $pre
     * @param  Collection<int, ValidatedEvidence>  $post
     * @param  list<string>  $retryOutcomes
     * @return array{0: InterventionResponseClassification, 1: ObservedImprovementSignal, 2: string, 3: string}
     */
    private function classify(
        ?LearningState $before,
        ?LearningState $after,
        ?LearningStateTransitionType $transition,
        Collection $pre,
        Collection $post,
        array $retryOutcomes,
    ): array {
        $hasPostState = $after !== null;
        $hasPostEvidence = $post->isNotEmpty();
        $hasRetrySignal = $retryOutcomes !== [];

        if (! $hasPostState && ! $hasPostEvidence && ! $hasRetrySignal) {
            return [
                InterventionResponseClassification::InsufficientEvidence,
                ObservedImprovementSignal::Inconclusive,
                'no_post_intervention_evidence_or_state → insufficient_evidence',
                'No subsequent usable evidence, Learning State, or linked retry outcome was observed after the intervention availability timestamp. Result is inconclusive. This does not claim the intervention was ineffective.',
            ];
        }

        if (! $hasPostState && ! $hasPostEvidence && $hasRetrySignal) {
            // Linked decision exists but no new evidence/state — thin.
            return [
                InterventionResponseClassification::NoObservedResponse,
                ObservedImprovementSignal::Inconclusive,
                'linked_retry_decision_without_new_evidence → no_observed_response',
                'A next-learning-action retry outcome is linked, but no subsequent usable evidence or Learning State was observed. Treated as no observed response (inconclusive improvement).',
            ];
        }

        $postSuccess = $post->contains(fn (ValidatedEvidence $item): bool => in_array($item->evidence_type, self::SUCCESS_TYPES, true));
        $postFailure = $post->contains(fn (ValidatedEvidence $item): bool => in_array($item->evidence_type, self::FAILURE_TYPES, true));
        $retrySuccess = in_array('success', $retryOutcomes, true);
        $retryFailure = in_array('failure', $retryOutcomes, true);

        if ($transition === LearningStateTransitionType::PositiveTransition) {
            if ($this->hasUnresolvedIndicators($after)) {
                return [
                    InterventionResponseClassification::PartialResponse,
                    ObservedImprovementSignal::ObservedImprovement,
                    'positive_state_transition_with_unresolved_indicators → partial_response',
                    'Observed Learning State transition indicates improvement (e.g. needs_support → progressing), but unresolved cognitive/psychomotor indicators remain. Labeled partial_response. Observed improvement after intervention — not a causal claim.',
                ];
            }

            return [
                InterventionResponseClassification::PositiveResponse,
                ObservedImprovementSignal::ObservedImprovement,
                'needs_support_to_progressing_or_stable → positive_response / observed_improvement',
                'Observed positive Learning State transition after intervention availability (e.g. needs_support → progressing/stable). This is an observed improvement pattern after intervention, not proof that the intervention caused the improvement.',
            ];
        }

        if ($transition === LearningStateTransitionType::Stabilization) {
            return [
                InterventionResponseClassification::PositiveResponse,
                ObservedImprovementSignal::StabilizationSignal,
                'progressing_to_stable → positive_response / stabilization_signal',
                'Observed stabilization transition (progressing → stable) after intervention availability. Observed improvement/stabilization signal — not causal effectiveness.',
            ];
        }

        if ($transition === LearningStateTransitionType::DeteriorationSignal) {
            return [
                InterventionResponseClassification::NegativeOrPersistentDifficulty,
                ObservedImprovementSignal::DeteriorationSignal,
                'stable_or_progressing_to_needs_support → negative_or_persistent_difficulty',
                'Observed deterioration signal in Learning State after intervention availability. This is an observed pattern, not a causal attribution.',
            ];
        }

        if ($transition === LearningStateTransitionType::PersistentSupportNeed
            || ($before?->state === LearningStateValue::NeedsSupport && $after?->state === LearningStateValue::NeedsSupport)) {
            if ($postSuccess || $retrySuccess) {
                return [
                    InterventionResponseClassification::PartialResponse,
                    ObservedImprovementSignal::NoObservedImprovement,
                    'persistent_needs_support_with_some_success_evidence → partial_response',
                    'Learning State remains needs_support, but some successful post-intervention outcome evidence exists. Labeled partial_response without claiming full recovery.',
                ];
            }

            return [
                InterventionResponseClassification::NegativeOrPersistentDifficulty,
                ObservedImprovementSignal::NoObservedImprovement,
                'needs_support_to_needs_support → negative_or_persistent_difficulty',
                'Learning State remained needs_support after intervention availability, with continued difficulty evidence and no observed recovery. No observed improvement — not a causal claim about intervention failure.',
            ];
        }

        // Evidence-only paths when state transition is missing/ambiguous.
        if ($postSuccess || $retrySuccess) {
            if ($postFailure || $retryFailure || $this->hasUnresolvedIndicators($after)) {
                return [
                    InterventionResponseClassification::PartialResponse,
                    ObservedImprovementSignal::ObservedImprovement,
                    'mixed_post_success_and_remaining_difficulty → partial_response',
                    'Post-intervention evidence includes successful outcomes alongside remaining difficulty signals. Observed partial response — not causal effectiveness.',
                ];
            }

            return [
                InterventionResponseClassification::PositiveResponse,
                ObservedImprovementSignal::ObservedImprovement,
                'post_intervention_successful_outcome → positive_response',
                'Successful post-intervention outcome evidence was observed. Observed improvement after intervention — not proof of causal effect.',
            ];
        }

        if ($postFailure || $retryFailure) {
            return [
                InterventionResponseClassification::NegativeOrPersistentDifficulty,
                ObservedImprovementSignal::NoObservedImprovement,
                'post_intervention_failure_without_recovery → negative_or_persistent_difficulty',
                'Post-intervention failure evidence was observed without recovery. No observed improvement.',
            ];
        }

        if ($hasPostEvidence || $hasPostState) {
            return [
                InterventionResponseClassification::NoObservedResponse,
                ObservedImprovementSignal::Inconclusive,
                'post_data_without_clear_improvement_or_deterioration → no_observed_response',
                'Subsequent data exists but does not clearly indicate improvement or persistent difficulty under V1 rules. Inconclusive.',
            ];
        }

        return [
            InterventionResponseClassification::InsufficientEvidence,
            ObservedImprovementSignal::Inconclusive,
            'ambiguous_post_intervention_pattern → insufficient_evidence',
            'Evidence after intervention is insufficient for a responsible V1 response classification.',
        ];
    }

    private function hasUnresolvedIndicators(?LearningState $state): bool
    {
        if ($state === null) {
            return false;
        }

        return in_array($state->cognitive_indicator, self::UNRESOLVED_COGNITIVE, true)
            || in_array($state->psychomotor_indicator, self::UNRESOLVED_PSYCHOMOTOR, true);
    }

    private function isUsable(ValidatedEvidence $evidence): bool
    {
        if ($evidence->quality === EvidenceQuality::Uncertain) {
            return false;
        }

        if ($evidence->evidence_category === EvidenceCategory::SystemContext) {
            return false;
        }

        return true;
    }

    /**
     * @param  Collection<int, ValidatedEvidence>  $post
     */
    private function confidence(?LearningState $before, ?LearningState $after, Collection $post): string
    {
        if ($before === null || ($after === null && $post->isEmpty())) {
            return 'low';
        }

        if ($after !== null && $post->isNotEmpty()) {
            return 'high';
        }

        return 'medium';
    }

    /**
     * @return array<string, bool>
     */
    private function analysisBoundary(): array
    {
        return [
            'analyzes_observed_intervention_response' => true,
            'claims_causal_effectiveness' => false,
            'claims_treatment_effect' => false,
            'claims_intervention_caused_improvement' => false,
            'claims_statistical_significance' => false,
            'performs_contextual_variation_analysis' => false,
            'performs_research_export' => false,
            'uses_ml_or_llm' => false,
            'mutates_m4' => false,
            'persists_response_table' => false,
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $value->value;
        }

        return (string) $value;
    }
}
