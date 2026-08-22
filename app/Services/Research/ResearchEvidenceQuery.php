<?php

namespace App\Services\Research;

use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Read-only research evidence foundation (M5-01).
 *
 * Assembles provenance and context from existing M3/M4 source-of-truth records.
 * Does not create Learning States, interventions, next actions, or analytics.
 */
final class ResearchEvidenceQuery
{
    /**
     * Deterministic pseudonymized research learner identifier.
     *
     * Does not alter authentication identity.
     */
    public function researchLearnerId(int $userId): string
    {
        return hash_hmac('sha256', 'research_learner|'.$userId, (string) config('app.key'));
    }

    /**
     * Research context derived from existing Activity hierarchy and programming profile.
     *
     * Unavailable fields (campus, institution, cohort) are returned as null — never invented.
     *
     * @return array{
     *     research_learner_id: ?string,
     *     learner_id: ?int,
     *     campus: null,
     *     institution: null,
     *     cohort: null,
     *     course_id: ?int,
     *     course_title: ?string,
     *     module_id: ?int,
     *     module_title: ?string,
     *     learning_unit_id: ?int,
     *     learning_unit_title: ?string,
     *     activity_id: int,
     *     activity_title: ?string,
     *     activity_type: ?string,
     *     programming_language: ?string,
     *     programming_language_display: ?string,
     *     session_id: null,
     *     bloom_demand: ?string,
     *     dave_demand: ?string,
     *     bloom_semantics: string,
     *     dave_semantics: string,
     *     concept: ?string,
     *     learning_objective: ?string,
     *     difficulty: ?string
     * }
     */
    public function researchContextForActivity(Activity $activity, ?int $userId = null): array
    {
        $activity->loadMissing([
            'learningUnit.module.course',
            'programmingActivity.languageExecutionProfile',
        ]);

        $unit = $activity->learningUnit;
        $module = $unit?->module;
        $course = $module?->course;
        $profile = $activity->programmingActivity?->languageExecutionProfile;

        return [
            'research_learner_id' => $userId !== null ? $this->researchLearnerId($userId) : null,
            'learner_id' => $userId,
            'campus' => null,
            'institution' => null,
            'cohort' => null,
            'course_id' => $course?->id,
            'course_title' => $course?->title,
            'module_id' => $module?->id,
            'module_title' => $module?->title,
            'learning_unit_id' => $unit?->id,
            'learning_unit_title' => $unit?->title,
            'activity_id' => $activity->id,
            'activity_title' => $activity->title,
            'activity_type' => $this->enumValue($activity->type),
            'programming_language' => $profile?->identifier,
            'programming_language_display' => $profile?->display_name,
            'session_id' => null,
            'bloom_demand' => $this->enumValue($activity->bloom_demand ?? $activity->getBloomDemand()),
            'dave_demand' => $this->enumValue($activity->dave_demand ?? $activity->getDaveDemand()),
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'concept' => $activity->getConcept(),
            'learning_objective' => $activity->getLearningObjective(),
            'difficulty' => $activity->getDifficulty(),
        ];
    }

    /**
     * Provenance: ValidatedEvidence → LearningEvent (+ quality/confidence preserved).
     *
     * @return array<string, mixed>
     */
    public function evidenceProvenance(ValidatedEvidence $evidence): array
    {
        $evidence->loadMissing(['learningEvent', 'activity', 'user']);

        $event = $evidence->learningEvent;

        return [
            'validated_evidence_id' => $evidence->id,
            'learning_event_id' => $evidence->learning_event_id,
            'learning_event' => $event === null ? null : [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
                'session_id' => $event->session_id,
                'course_id' => $event->course_id,
                'activity_id' => $event->activity_id,
                'user_id' => $event->user_id,
            ],
            'user_id' => $evidence->user_id,
            'research_learner_id' => $this->researchLearnerId((int) $evidence->user_id),
            'activity_id' => $evidence->activity_id,
            'evidence_category' => $this->enumValue($evidence->evidence_category),
            'evidence_type' => $evidence->evidence_type,
            'quality' => $this->enumValue($evidence->quality),
            'confidence' => $this->enumValue($evidence->confidence),
            'validation_reason' => $evidence->validation_reason,
            'source_record_type' => $evidence->source_record_type,
            'source_record_id' => $evidence->source_record_id,
            'observed_value' => $evidence->observed_value,
            'context_summary' => $evidence->context_summary,
            'validated_at' => optional($evidence->validated_at)?->toIso8601String(),
            'pedagogical_at' => $this->evidencePedagogicalAt($evidence)?->toIso8601String(),
            'pedagogical_at_semantics' => $event?->occurred_at
                ? 'learning_event.occurred_at'
                : 'validated_evidence.validated_at',
        ];
    }

    /**
     * Provenance: LearningState → ValidatedEvidence → LearningEvent.
     *
     * @return array<string, mixed>
     */
    public function learningStateProvenance(LearningState $state): array
    {
        $state->loadMissing(['validatedEvidence.learningEvent', 'activity', 'user']);

        $evidenceRows = $state->validatedEvidence
            ->sortBy('id')
            ->values()
            ->map(fn (ValidatedEvidence $evidence): array => $this->evidenceProvenance($evidence))
            ->all();

        return [
            'learning_state_id' => $state->id,
            'inference_key' => $state->inference_key,
            'user_id' => $state->user_id,
            'research_learner_id' => $this->researchLearnerId((int) $state->user_id),
            'activity_id' => $state->activity_id,
            'state' => $this->enumValue($state->state),
            'state_confidence' => $this->enumValue($state->state_confidence),
            'bloom_demand' => $this->enumValue($state->bloom_demand),
            'dave_demand' => $this->enumValue($state->dave_demand),
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'cognitive_indicator' => $state->cognitive_indicator,
            'psychomotor_indicator' => $state->psychomotor_indicator,
            'behavioral_indicators' => $state->behavioral_indicators,
            'fusion_summary' => $state->fusion_summary,
            'explanation' => $state->explanation,
            'inference_rule' => $state->inference_rule,
            'inferred_at' => optional($state->inferred_at)?->toIso8601String(),
            'validated_evidence' => $evidenceRows,
            'validated_evidence_ids' => $state->validatedEvidence->pluck('id')->sort()->values()->all(),
            'learning_event_ids' => $state->validatedEvidence
                ->pluck('learning_event_id')
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * Provenance: AdaptiveIntervention → LearningState → Evidence.
     *
     * @return array<string, mixed>
     */
    public function interventionProvenance(AdaptiveIntervention $intervention): array
    {
        $intervention->loadMissing(['learningState.validatedEvidence.learningEvent', 'activity', 'user']);

        $state = $intervention->learningState;

        return [
            'adaptive_intervention_id' => $intervention->id,
            'intervention_key' => $intervention->intervention_key,
            'user_id' => $intervention->user_id,
            'research_learner_id' => $this->researchLearnerId((int) $intervention->user_id),
            'activity_id' => $intervention->activity_id,
            'learning_state_id' => $intervention->learning_state_id,
            'intervention_type' => $this->enumValue($intervention->intervention_type),
            'socratic_type' => $this->enumValue($intervention->socratic_type),
            'target_state' => $this->enumValue($intervention->target_state),
            'reason' => $intervention->reason,
            'selection_rule' => $intervention->selection_rule,
            'is_strong' => (bool) $intervention->is_strong,
            'is_remedial' => (bool) $intervention->is_remedial,
            'created_at' => optional($intervention->created_at)?->toIso8601String(),
            'learning_state' => $state ? $this->learningStateProvenance($state) : null,
        ];
    }

    /**
     * Provenance: NextLearningAction → LearningState / Intervention / Evidence.
     *
     * @return array<string, mixed>
     */
    public function nextActionProvenance(NextLearningAction $action): array
    {
        $action->loadMissing([
            'learningState.validatedEvidence.learningEvent',
            'adaptiveIntervention',
            'activity',
            'user',
        ]);

        $state = $action->learningState;
        $intervention = $action->adaptiveIntervention;

        return [
            'next_learning_action_id' => $action->id,
            'decision_key' => $action->decision_key,
            'user_id' => $action->user_id,
            'research_learner_id' => $this->researchLearnerId((int) $action->user_id),
            'activity_id' => $action->activity_id,
            'learning_state_id' => $action->learning_state_id,
            'adaptive_intervention_id' => $action->adaptive_intervention_id,
            'action' => $this->enumValue($action->action),
            'reason' => $action->reason,
            'decision_rule' => $action->decision_rule,
            'retry_outcome' => $action->retry_outcome,
            'decided_at' => optional($action->decided_at)?->toIso8601String(),
            'learning_state' => $state ? $this->learningStateProvenance($state) : null,
            'adaptive_intervention' => $intervention ? [
                'id' => $intervention->id,
                'intervention_type' => $this->enumValue($intervention->intervention_type),
                'selection_rule' => $intervention->selection_rule,
                'reason' => $intervention->reason,
            ] : null,
        ];
    }

    /**
     * Historical Learning States for a learner (append-friendly; ordered chronologically).
     *
     * Chronology uses inferred_at, then id. Does not invent trajectory analysis.
     *
     * @return Collection<int, LearningState>
     */
    public function learningStateHistory(int $userId, ?int $activityId = null): Collection
    {
        $query = LearningState::query()
            ->with(['validatedEvidence.learningEvent', 'activity', 'adaptiveInterventions', 'nextLearningActions'])
            ->where('user_id', $userId)
            ->orderBy('inferred_at')
            ->orderBy('id');

        if ($activityId !== null) {
            $query->where('activity_id', $activityId);
        }

        return $query->get();
    }

    /**
     * Chronological validated evidence timeline for a learner.
     *
     * Pedagogical time prefers LearningEvent.occurred_at over validated_at.
     *
     * @return Collection<int, ValidatedEvidence>
     */
    public function evidenceTimeline(int $userId, ?int $activityId = null): Collection
    {
        $query = ValidatedEvidence::query()
            ->with(['learningEvent', 'activity'])
            ->where('user_id', $userId);

        if ($activityId !== null) {
            $query->where('activity_id', $activityId);
        }

        return $query->get()
            ->sortBy(function (ValidatedEvidence $evidence): string {
                $at = $this->evidencePedagogicalAt($evidence);

                return ($at?->format('Y-m-d H:i:s.u') ?? '9999').'|'.str_pad((string) $evidence->id, 12, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    /**
     * Closed-loop trace for one Learning State using existing FK provenance.
     *
     * Does not persist or invent cycle_id tables.
     *
     * @return array<string, mixed>
     */
    public function closedLoopTrace(LearningState $state): array
    {
        $state->loadMissing([
            'validatedEvidence.learningEvent',
            'adaptiveInterventions',
            'nextLearningActions.adaptiveIntervention',
            'activity',
        ]);

        $interventions = $state->adaptiveInterventions->sortBy('id')->values();
        $actions = $state->nextLearningActions->sortBy('id')->values();

        return [
            'learning_state' => $this->learningStateProvenance($state),
            'adaptive_interventions' => $interventions
                ->map(fn (AdaptiveIntervention $intervention): array => $this->interventionProvenance($intervention))
                ->all(),
            'next_learning_actions' => $actions
                ->map(fn (NextLearningAction $action): array => $this->nextActionProvenance($action))
                ->all(),
            'research_context' => $this->researchContextForActivity($state->activity, (int) $state->user_id),
            'creates_research_copy' => false,
            'performs_longitudinal_analysis' => false,
            'mutates_m4' => false,
        ];
    }

    /**
     * Assemble a read-only research view for learner + activity without creating records.
     *
     * @return array<string, mixed>
     */
    public function assembleForLearnerActivity(int $userId, int $activityId): array
    {
        $activity = Activity::query()->findOrFail($activityId);

        $states = $this->learningStateHistory($userId, $activityId);
        $evidence = $this->evidenceTimeline($userId, $activityId);
        $events = LearningEvent::query()
            ->where('user_id', $userId)
            ->where('activity_id', $activityId)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $interventions = AdaptiveIntervention::query()
            ->where('user_id', $userId)
            ->where('activity_id', $activityId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $actions = NextLearningAction::query()
            ->where('user_id', $userId)
            ->where('activity_id', $activityId)
            ->orderBy('decided_at')
            ->orderBy('id')
            ->get();

        return [
            'research_learner_id' => $this->researchLearnerId($userId),
            'learner_id' => $userId,
            'research_context' => $this->researchContextForActivity($activity, $userId),
            'learning_events' => $events->map(fn (LearningEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
                'session_id' => $event->session_id,
                'course_id' => $event->course_id,
            ])->all(),
            'validated_evidence' => $evidence
                ->map(fn (ValidatedEvidence $row): array => $this->evidenceProvenance($row))
                ->all(),
            'learning_states' => $states
                ->map(fn (LearningState $state): array => $this->learningStateProvenance($state))
                ->all(),
            'adaptive_interventions' => $interventions
                ->map(fn (AdaptiveIntervention $intervention): array => [
                    'id' => $intervention->id,
                    'learning_state_id' => $intervention->learning_state_id,
                    'intervention_type' => $this->enumValue($intervention->intervention_type),
                    'selection_rule' => $intervention->selection_rule,
                    'created_at' => optional($intervention->created_at)?->toIso8601String(),
                ])
                ->all(),
            'next_learning_actions' => $actions
                ->map(fn (NextLearningAction $action): array => [
                    'id' => $action->id,
                    'learning_state_id' => $action->learning_state_id,
                    'adaptive_intervention_id' => $action->adaptive_intervention_id,
                    'action' => $this->enumValue($action->action),
                    'decision_rule' => $action->decision_rule,
                    'decided_at' => optional($action->decided_at)?->toIso8601String(),
                ])
                ->all(),
            'data_gaps' => [
                'campus' => true,
                'institution' => true,
                'cohort' => true,
                'persistent_cycle_id' => true,
                'learning_session_id_rarely_populated' => true,
            ],
        ];
    }

    private function evidencePedagogicalAt(ValidatedEvidence $evidence): ?\Illuminate\Support\Carbon
    {
        $evidence->loadMissing('learningEvent');

        return $evidence->learningEvent?->occurred_at ?? $evidence->validated_at;
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
