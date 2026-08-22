<?php

namespace App\Services\Research;

use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Models\AdaptiveIntervention;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Facades\DB;

/**
 * Thin closed-loop orchestration for M4-T06.
 *
 * Delegates to existing T02–T05 services. Does not own inference, intervention,
 * or next-action rules. Does not create reassessment questions or run M5 analytics.
 */
final class NexusClosedLoopService
{
    public function __construct(
        private readonly LearningStateInferenceService $stateInference,
        private readonly AdaptiveInterventionService $interventionService,
        private readonly NextLearningActionService $nextActionService,
    ) {}

    /**
     * Run one closed-loop processing pass for the learner on an activity.
     *
     * Assumes LearningEvents (and thus T02 ValidatedEvidence) already exist.
     * Creates a remedial intervention only for needs_support.
     *
     * @param  'success'|'failure'|null  $retryOutcome
     * @return array{
     *     cycle_id: string,
     *     learning_state: LearningState,
     *     intervention: ?AdaptiveIntervention,
     *     next_action: NextLearningAction,
     *     validated_evidence_ids: list<int>,
     *     remedial_intervention_created: bool,
     *     provenance: array<string, mixed>
     * }
     */
    public function processLearnerActivity(
        int $userId,
        int $activityId,
        ?AdaptiveIntervention $priorIntervention = null,
        ?string $retryOutcome = null,
    ): array {
        return DB::transaction(function () use ($userId, $activityId, $priorIntervention, $retryOutcome): array {
            $state = $this->stateInference->inferForLearnerActivity($userId, $activityId);

            $intervention = null;
            $remedialCreated = false;
            $isPostRetryPass = $retryOutcome !== null && $priorIntervention !== null;

            // Initial needs_support pass creates T04 intervention.
            // Post-retry pass goes T03 → T05 with the prior intervention (no second T04 in the same response cycle).
            if (! $isPostRetryPass && $state->state === LearningStateValue::NeedsSupport) {
                $intervention = $this->interventionService->createForLearningState($state);
                $remedialCreated = $intervention->is_remedial;
            }

            $decisionIntervention = $isPostRetryPass ? $priorIntervention : ($intervention ?? $priorIntervention);
            $nextAction = $this->nextActionService->decideForLearningState(
                $state,
                $decisionIntervention,
                $retryOutcome,
            );

            $traceIntervention = $intervention ?? ($isPostRetryPass ? $priorIntervention : null);
            $evidenceIds = $state->validatedEvidence->pluck('id')->sort()->values()->all();
            $cycleId = $this->cycleId($userId, $activityId, $state, $traceIntervention, $nextAction, $evidenceIds);

            return [
                'cycle_id' => $cycleId,
                'learning_state' => $state->fresh(['validatedEvidence', 'activity', 'user']),
                'intervention' => $traceIntervention?->fresh(['learningState']),
                'next_action' => $nextAction->fresh(['learningState', 'adaptiveIntervention']),
                'validated_evidence_ids' => $evidenceIds,
                'remedial_intervention_created' => $remedialCreated,
                'provenance' => $this->provenance($state, $traceIntervention, $nextAction, $evidenceIds, $cycleId),
            ];
        });
    }

    /**
     * Record a new observable learning event (triggers T02 validation), then continue the loop.
     *
     * @param  array<string, mixed>|null  $payload
     * @param  'success'|'failure'|null  $retryOutcome
     * @return array{
     *     cycle_id: string,
     *     learning_event: LearningEvent,
     *     learning_state: LearningState,
     *     intervention: ?AdaptiveIntervention,
     *     next_action: NextLearningAction,
     *     validated_evidence_ids: list<int>,
     *     remedial_intervention_created: bool,
     *     provenance: array<string, mixed>
     * }
     */
    public function recordEvidenceAndProcess(
        string $eventType,
        int $userId,
        int $courseId,
        int $activityId,
        ?array $payload = null,
        ?AdaptiveIntervention $priorIntervention = null,
        ?string $retryOutcome = null,
    ): array {
        $event = LearningEvent::record($eventType, $userId, $courseId, $activityId, $payload);

        $result = $this->processLearnerActivity(
            $userId,
            $activityId,
            $priorIntervention,
            $retryOutcome,
        );

        $result['learning_event'] = $event->fresh(['validatedEvidence']);

        return $result;
    }

    /**
     * After an intervention, process a retry outcome with newly recorded evidence already present.
     *
     * @param  'success'|'failure'  $retryOutcome
     * @return array{
     *     cycle_id: string,
     *     learning_state: LearningState,
     *     intervention: ?AdaptiveIntervention,
     *     next_action: NextLearningAction,
     *     validated_evidence_ids: list<int>,
     *     remedial_intervention_created: bool,
     *     provenance: array<string, mixed>
     * }
     */
    public function processAfterRetry(
        int $userId,
        int $activityId,
        AdaptiveIntervention $intervention,
        string $retryOutcome,
    ): array {
        return $this->processLearnerActivity(
            $userId,
            $activityId,
            $intervention,
            $retryOutcome,
        );
    }

    /**
     * @param  list<int>  $evidenceIds
     */
    private function cycleId(
        int $userId,
        int $activityId,
        LearningState $state,
        ?AdaptiveIntervention $intervention,
        NextLearningAction $nextAction,
        array $evidenceIds,
    ): string {
        return hash(
            'sha256',
            $userId.'|'
            .$activityId.'|'
            .$state->inference_key.'|'
            .($intervention?->intervention_key ?? 'none').'|'
            .$nextAction->decision_key.'|'
            .implode(',', $evidenceIds)
        );
    }

    /**
     * @param  list<int>  $evidenceIds
     * @return array<string, mixed>
     */
    private function provenance(
        LearningState $state,
        ?AdaptiveIntervention $intervention,
        NextLearningAction $nextAction,
        array $evidenceIds,
        string $cycleId,
    ): array {
        $events = ValidatedEvidence::query()
            ->whereIn('id', $evidenceIds)
            ->orderBy('id')
            ->get(['id', 'learning_event_id', 'evidence_type', 'quality']);

        return [
            'cycle_id' => $cycleId,
            'learning_event_ids' => $events->pluck('learning_event_id')->unique()->values()->all(),
            'validated_evidence_ids' => $evidenceIds,
            'learning_state_id' => $state->id,
            'learning_state' => $state->state->value,
            'adaptive_intervention_id' => $intervention?->id,
            'next_learning_action_id' => $nextAction->id,
            'next_action' => $nextAction->action instanceof NextLearningActionType
                ? $nextAction->action->value
                : (string) $nextAction->action,
            'creates_reassessment_question' => false,
            'longitudinal_analysis' => false,
            'ml_or_llm_orchestration' => false,
        ];
    }
}
