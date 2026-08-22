<?php

namespace App\Services\Evaluation\LearningState;

use App\Enums\ActivityType;
use App\Enums\LearningStateValue;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes the real M4-T03 LearningStateInferenceService against a synthetic
 * scenario and captures the actual Learning State without persisting anything
 * (M6-02).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION:
 * Fixtures, seeded evidence, and every inference write happen inside a database
 * transaction that is ALWAYS rolled back. The actual outcome is captured into
 * detached scalar/array data before rollback, so validation leaves the production
 * learning source-of-truth unchanged. The runner calls only the authoritative
 * inference entry point and never mutates existing records or M4 rules.
 *
 * Evidence is seeded directly at the ValidatedEvidence layer (exactly as the
 * existing T03 unit tests do) so the overlay can validate T03 across evidence
 * quality/confidence boundaries. This does not modify T02/T03 behavior.
 */
final class LearningStateValidationRunner
{
    public function __construct(
        private readonly LearningStateInferenceService $inference,
        private readonly LearningStateComparator $comparator,
        private readonly LearningStateConstraintChecker $constraintChecker,
    ) {}

    /**
     * @param  list<LearningStateScenario>  $scenarios
     * @return list<LearningStateValidationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (LearningStateScenario $s): LearningStateValidationResult => $this->run($s), $scenarios);
    }

    public function run(LearningStateScenario $scenario): LearningStateValidationResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            $actual = $this->captureActual($scenario);
        } catch (Throwable $e) {
            $failureHandled = true;
            $actual = $this->errorOutcome($scenario, $e);
            $notes[] = 'scenario execution raised an exception and was contained: '.$e->getMessage();
        }

        $comparison = $this->comparator->compare($scenario->expected, $actual);
        $constraintCheck = $this->constraintChecker->check($actual);
        $provenanceCheck = $this->checkProvenance($scenario, $actual);

        $status = $comparison['status'];
        $dimensions = $comparison['dimensions'];
        $differences = $comparison['differences'];

        $dimensions['provenance'] = $provenanceCheck['traceable'] ? 'pass' : 'fail';
        $dimensions['idempotency'] = $actual->idempotent ? 'pass' : 'fail';
        $dimensions['boundary'] = $scenario->category === 'boundary'
            ? ($this->isDefinedState($actual) ? 'pass' : 'fail')
            : 'not_applicable';
        $dimensions['rule_compliance'] = $constraintCheck['compliant'] ? 'pass' : 'fail';
        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['failure_handling'] = $failureHandled ? 'review' : 'pass';

        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(fn (string $v): string => 'constraint: '.$v, $constraintCheck['violations']));
            $status = EvaluationStatus::Fail;
        }

        if (! $provenanceCheck['traceable']) {
            $differences[] = 'provenance: actual state is not fully traceable back to source evidence/events';
            $status = EvaluationStatus::Fail;
        }

        if (! $actual->idempotent) {
            $differences[] = 'idempotency: repeated inference on the same evidence produced a different or duplicated state';
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new LearningStateValidationResult(
            scenarioId: $scenario->scenarioId,
            category: $scenario->category,
            status: $status,
            expected: $scenario->expected,
            actual: $actual,
            differences: array_values($differences),
            dimensions: $dimensions,
            provenanceCheck: $provenanceCheck,
            constraintCheck: $constraintCheck,
            notes: $notes,
        );
    }

    private function captureActual(LearningStateScenario $scenario): ActualLearningState
    {
        DB::beginTransaction();

        try {
            [$student, $course, $activity] = $this->seedFixtures($scenario);

            foreach ($scenario->evidence as $spec) {
                $this->seedEvidence($student->id, $course->id, $activity->id, $spec);
            }

            $first = $this->inference->inferForLearnerActivity($student->id, $activity->id);
            $second = $this->inference->inferForLearnerActivity($student->id, $activity->id);

            $idempotent = $first->id === $second->id
                && $first->inference_key === $second->inference_key
                && LearningState::query()->count() === 1;

            $interventionCount = AdaptiveIntervention::query()->count();

            return $this->toActualLearningState($scenario, $first, $idempotent, $interventionCount);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function seedFixtures(LearningStateScenario $scenario): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()
            ->for($unit, 'learningUnit')
            ->published()
            ->type(ActivityType::CodingExercise)
            ->create([
                'difficulty' => 'medium',
                'concept' => $scenario->concept,
                'learning_objective' => 'Synthetic validation objective for '.$scenario->concept.'.',
                'bloom_demand' => $scenario->bloomDemand,
                'dave_demand' => $scenario->daveDemand,
            ]);

        return [$student, $course, $activity];
    }

    private function seedEvidence(int $userId, int $courseId, int $activityId, EvidenceSpec $spec): ValidatedEvidence
    {
        $event = LearningEvent::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => match ($spec->evidenceType) {
                'repeated_submission_failures' => 'submission_rejected',
                'repeated_execution' => 'code_run',
                'execution_runtime_failure', 'execution_timeout', 'execution_system_anomaly' => 'code_run',
                default => $spec->evidenceType,
            },
            'payload' => ['synthetic' => true],
            'occurred_at' => now(),
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'source_record_type' => null,
            'source_record_id' => null,
            'evidence_category' => $spec->category->value,
            'evidence_type' => $spec->evidenceType,
            'observed_value' => ['summary' => $spec->evidenceType],
            'context_summary' => [
                'task_repetition' => 'new',
                'task_difficulty' => 'medium',
                'execution_anomaly' => 'none',
                'network_environment' => 'unknown',
            ],
            'quality' => $spec->quality->value,
            'confidence' => $spec->confidence->value,
            'validation_reason' => 'Synthetic validated evidence for M6-02 evaluation.',
            'validated_at' => now(),
        ]);
    }

    private function toActualLearningState(
        LearningStateScenario $scenario,
        LearningState $state,
        bool $idempotent,
        int $interventionCount,
    ): ActualLearningState {
        $evidence = $state->validatedEvidence;
        $evidenceIds = $evidence->pluck('id')->sort()->values()->all();
        $eventIds = $evidence->pluck('learning_event_id')->filter()->unique()->sort()->values()->all();

        return new ActualLearningState(
            learnerRef: $this->learnerRef($scenario),
            state: $state->state->value,
            stateConfidence: $state->state_confidence->value,
            bloomDemand: $state->bloom_demand?->value,
            daveDemand: $state->dave_demand?->value,
            cognitiveIndicator: $state->cognitive_indicator,
            psychomotorIndicator: $state->psychomotor_indicator,
            behavioralIndicators: is_array($state->behavioral_indicators) ? array_values($state->behavioral_indicators) : [],
            inferenceRule: (string) $state->inference_rule,
            explanation: (string) $state->explanation,
            fusionSummary: is_array($state->fusion_summary) ? $state->fusion_summary : [],
            idempotent: $idempotent,
            interventionCountAfterInference: $interventionCount,
            provenance: [
                'scenario_id' => $scenario->scenarioId,
                'learning_state_id' => $state->id,
                'validated_evidence_ids' => $evidenceIds,
                'learning_event_ids' => $eventIds,
                'inference_key' => $state->inference_key,
                'ml_or_llm' => false,
                'creates_intervention' => false,
            ],
        );
    }

    private function errorOutcome(LearningStateScenario $scenario, Throwable $e): ActualLearningState
    {
        return new ActualLearningState(
            learnerRef: $this->learnerRef($scenario),
            state: 'error',
            stateConfidence: 'low',
            bloomDemand: $scenario->bloomDemand?->value,
            daveDemand: $scenario->daveDemand?->value,
            cognitiveIndicator: null,
            psychomotorIndicator: null,
            behavioralIndicators: [],
            inferenceRule: 'error',
            explanation: '',
            fusionSummary: [],
            idempotent: false,
            interventionCountAfterInference: 0,
            provenance: [
                'scenario_id' => $scenario->scenarioId,
                'error' => $e->getMessage(),
                'ml_or_llm' => false,
                'creates_intervention' => false,
            ],
        );
    }

    private function isDefinedState(ActualLearningState $actual): bool
    {
        return LearningStateValue::tryFrom($actual->state) !== null;
    }

    private function learnerRef(LearningStateScenario $scenario): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-02|'.$scenario->scenarioId), 0, 12);
    }

    /**
     * @return array{
     *     scenario_id: string,
     *     has_learning_state: bool,
     *     evidence_present: bool,
     *     events_present: bool,
     *     chain_consistent: bool,
     *     links_back_to_scenario: bool,
     *     traceable: bool
     * }
     */
    private function checkProvenance(LearningStateScenario $scenario, ActualLearningState $actual): array
    {
        $provenance = $actual->provenance;

        $hasLearningState = ($provenance['learning_state_id'] ?? null) !== null;
        $evidencePresent = count($provenance['validated_evidence_ids'] ?? []) > 0;
        $eventsPresent = count($provenance['learning_event_ids'] ?? []) > 0;
        $chainConsistent = $evidencePresent ? $eventsPresent : true;
        $linksBack = ($provenance['scenario_id'] ?? null) === $scenario->scenarioId;

        return [
            'scenario_id' => $scenario->scenarioId,
            'has_learning_state' => $hasLearningState,
            'evidence_present' => $evidencePresent,
            'events_present' => $eventsPresent,
            'chain_consistent' => $chainConsistent,
            'links_back_to_scenario' => $linksBack,
            'traceable' => $hasLearningState && $chainConsistent && $linksBack,
        ];
    }
}
