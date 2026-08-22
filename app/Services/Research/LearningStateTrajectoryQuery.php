<?php

namespace App\Services\Research;

use App\Enums\LearningStateTransitionType;
use App\Enums\LearningStateValue;
use App\Models\Activity;
use App\Models\LearningState;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Deterministic Learning State trajectory analysis (M5-02).
 *
 * Derived/read-only longitudinal layer over existing LearningState history.
 * Does not infer states, create interventions, detect weak areas, or claim causality.
 */
final class LearningStateTrajectoryQuery
{
    public function __construct(
        private readonly ResearchEvidenceQuery $researchEvidence,
    ) {}

    /**
     * Trajectory for one learner on one activity.
     *
     * @return array<string, mixed>
     */
    public function forLearnerActivity(int $userId, int $activityId): array
    {
        $states = $this->orderedStates(
            LearningState::query()
                ->with([
                    'validatedEvidence.learningEvent',
                    'activity.learningUnit.module.course',
                    'activity.programmingActivity.languageExecutionProfile',
                ])
                ->where('user_id', $userId)
                ->where('activity_id', $activityId),
        );

        return $this->buildTrajectory(
            $userId,
            $states,
            [
                'type' => 'activity',
                'course_id' => $states->first()?->activity?->learningUnit?->module?->course_id
                    ?? Activity::query()->with('learningUnit.module')->find($activityId)?->learningUnit?->module?->course_id,
                'activity_id' => $activityId,
            ],
        );
    }

    /**
     * Trajectory for one learner within one course (never merges other courses).
     *
     * @return array<string, mixed>
     */
    public function forLearnerCourse(int $userId, int $courseId): array
    {
        $states = $this->orderedStates(
            LearningState::query()
                ->with([
                    'validatedEvidence.learningEvent',
                    'activity.learningUnit.module.course',
                    'activity.programmingActivity.languageExecutionProfile',
                ])
                ->where('user_id', $userId)
                ->whereHas('activity.learningUnit.module', function ($query) use ($courseId): void {
                    $query->where('course_id', $courseId);
                }),
        );

        return $this->buildTrajectory(
            $userId,
            $states,
            [
                'type' => 'course',
                'course_id' => $courseId,
                'activity_id' => null,
            ],
        );
    }

    /**
     * Learner-level view: one trajectory per course (no cross-course merge).
     *
     * @return list<array<string, mixed>>
     */
    public function forLearnerGroupedByCourse(int $userId): array
    {
        $courseIds = LearningState::query()
            ->where('user_id', $userId)
            ->with('activity.learningUnit.module')
            ->get()
            ->map(fn (LearningState $state): ?int => $state->activity?->learningUnit?->module?->course_id)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $courseIds
            ->map(fn (int $courseId): array => $this->forLearnerCourse($userId, $courseId))
            ->all();
    }

    /**
     * Classify a consecutive state pair using V1 deterministic rules.
     */
    public function classifyTransition(LearningStateValue $from, LearningStateValue $to): LearningStateTransitionType
    {
        if ($from === LearningStateValue::InsufficientEvidence
            || $to === LearningStateValue::InsufficientEvidence) {
            return LearningStateTransitionType::InsufficientOrAmbiguous;
        }

        return match ([$from, $to]) {
            [LearningStateValue::NeedsSupport, LearningStateValue::Progressing],
            [LearningStateValue::NeedsSupport, LearningStateValue::Stable] => LearningStateTransitionType::PositiveTransition,
            [LearningStateValue::Progressing, LearningStateValue::Stable] => LearningStateTransitionType::Stabilization,
            [LearningStateValue::NeedsSupport, LearningStateValue::NeedsSupport] => LearningStateTransitionType::PersistentSupportNeed,
            [LearningStateValue::Stable, LearningStateValue::NeedsSupport],
            [LearningStateValue::Progressing, LearningStateValue::NeedsSupport] => LearningStateTransitionType::DeteriorationSignal,
            [LearningStateValue::Stable, LearningStateValue::Stable] => LearningStateTransitionType::StableContinuation,
            [LearningStateValue::Progressing, LearningStateValue::Progressing] => LearningStateTransitionType::ContinuedProgressing,
            default => LearningStateTransitionType::InsufficientOrAmbiguous,
        };
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<LearningState>  $query
     * @return Collection<int, LearningState>
     */
    private function orderedStates($query): Collection
    {
        // Pedagogical chronology for Learning State: inferred_at (when M4 produced the state).
        // Tie-break: id ascending for deterministic ordering when inferred_at collides.
        return $query
            ->orderBy('inferred_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @param  array{type: string, course_id: ?int, activity_id: ?int}  $scope
     * @return array<string, mixed>
     */
    private function buildTrajectory(int $userId, Collection $states, array $scope): array
    {
        $stateRows = $states->map(fn (LearningState $state): array => $this->stateRow($state, $userId))->values();
        $transitions = [];

        for ($i = 1; $i < $states->count(); $i++) {
            /** @var LearningState $from */
            $from = $states[$i - 1];
            /** @var LearningState $to */
            $to = $states[$i];
            $transitions[] = $this->transitionRow($from, $to);
        }

        $allEvidenceIds = $states
            ->flatMap(fn (LearningState $state) => $state->validatedEvidence->pluck('id'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $allEventIds = $states
            ->flatMap(fn (LearningState $state) => $state->validatedEvidence->pluck('learning_event_id'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
            'learner_id' => $userId,
            'scope' => $scope,
            'timestamp_semantics' => [
                'primary' => 'learning_states.inferred_at',
                'tie_break' => 'learning_states.id',
                'reason' => 'inferred_at is the pedagogical time when M4-T03 produced the Learning State; id breaks ties deterministically.',
            ],
            'states' => $stateRows->all(),
            'sequence' => $stateRows->pluck('state')->all(),
            'transitions' => $transitions,
            'provenance' => [
                'learning_state_ids' => $states->pluck('id')->values()->all(),
                'validated_evidence_ids' => $allEvidenceIds,
                'learning_event_ids' => $allEventIds,
            ],
            'analysis_boundary' => [
                'observes_trajectory_patterns' => true,
                'claims_causal_improvement' => false,
                'claims_intervention_effectiveness' => false,
                'claims_treatment_effect' => false,
                'performs_weak_area_detection' => false,
                'performs_contextual_variation_analysis' => false,
                'mutates_m4' => false,
                'persists_trajectory_table' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stateRow(LearningState $state, int $userId): array
    {
        $evidenceIds = $state->validatedEvidence->pluck('id')->sort()->values()->all();
        $eventIds = $state->validatedEvidence->pluck('learning_event_id')->filter()->unique()->sort()->values()->all();

        return [
            'learning_state_id' => $state->id,
            'activity_id' => $state->activity_id,
            'state' => $this->enumValue($state->state),
            'state_confidence' => $this->enumValue($state->state_confidence),
            'inferred_at' => optional($state->inferred_at)?->toIso8601String(),
            'inference_key' => $state->inference_key,
            'inference_rule' => $state->inference_rule,
            'explanation' => $state->explanation,
            'bloom_demand' => $this->enumValue($state->bloom_demand),
            'dave_demand' => $this->enumValue($state->dave_demand),
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'validated_evidence_ids' => $evidenceIds,
            'learning_event_ids' => $eventIds,
            'context' => $state->activity
                ? $this->researchEvidence->researchContextForActivity($state->activity, $userId)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitionRow(LearningState $from, LearningState $to): array
    {
        /** @var LearningStateValue $fromState */
        $fromState = $from->state;
        /** @var LearningStateValue $toState */
        $toState = $to->state;

        $type = $this->classifyTransition($fromState, $toState);
        $rule = sprintf(
            '%s → %s = %s',
            $fromState->value,
            $toState->value,
            $type->value,
        );

        $fromEvidence = $from->validatedEvidence->pluck('id')->all();
        $toEvidence = $to->validatedEvidence->pluck('id')->all();

        return [
            'from_learning_state_id' => $from->id,
            'to_learning_state_id' => $to->id,
            'from_activity_id' => $from->activity_id,
            'to_activity_id' => $to->activity_id,
            'from_state' => $fromState->value,
            'to_state' => $toState->value,
            'from_inferred_at' => optional($from->inferred_at)?->toIso8601String(),
            'to_inferred_at' => optional($to->inferred_at)?->toIso8601String(),
            'from_state_confidence' => $this->enumValue($from->state_confidence),
            'to_state_confidence' => $this->enumValue($to->state_confidence),
            'transition_type' => $type->value,
            'transition_rule' => $rule,
            'explanation' => $this->explanationFor($type, $fromState, $toState),
            'source_evidence_ids' => collect($fromEvidence)
                ->merge($toEvidence)
                ->unique()
                ->sort()
                ->values()
                ->all(),
            'from_validated_evidence_ids' => collect($fromEvidence)->sort()->values()->all(),
            'to_validated_evidence_ids' => collect($toEvidence)->sort()->values()->all(),
            'claims_causal_improvement' => false,
            'claims_intervention_effectiveness' => false,
        ];
    }

    private function explanationFor(
        LearningStateTransitionType $type,
        LearningStateValue $from,
        LearningStateValue $to,
    ): string {
        $observed = sprintf(
            'Observed trajectory pattern: %s → %s (%s).',
            $from->value,
            $to->value,
            $type->value,
        );

        $boundary = ' This is an observed Learning State trajectory pattern, not a causal claim that an intervention caused improvement or a treatment effect.';

        return match ($type) {
            LearningStateTransitionType::PositiveTransition => $observed.' Labeled as observed positive state transition.'.$boundary,
            LearningStateTransitionType::Stabilization => $observed.' Labeled as stabilization.'.$boundary,
            LearningStateTransitionType::PersistentSupportNeed => $observed.' Labeled as persistent support need.',
            LearningStateTransitionType::DeteriorationSignal => $observed.' Labeled as deterioration signal (observed pattern only).'.$boundary,
            LearningStateTransitionType::StableContinuation => $observed.' Labeled as stable continuation.',
            LearningStateTransitionType::ContinuedProgressing => $observed.' Labeled as continued progressing.',
            LearningStateTransitionType::InsufficientOrAmbiguous => $observed.' Insufficient evidence or ambiguous consecutive pair for a specific V1 transition label.',
        };
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
