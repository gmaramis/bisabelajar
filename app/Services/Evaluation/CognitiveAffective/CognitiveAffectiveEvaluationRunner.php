<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Enums\ActivityType;
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
use App\Services\Evaluation\LearningState\EvidenceSpec;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes the real M4-T03 model against synthetic cognitive-affective scenarios
 * and captures the actual observable indicators without persisting anything (M6-04).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION: fixtures, seeded evidence, and every
 * inference write happen inside a transaction that is ALWAYS rolled back; the
 * actual outcome is captured into privacy-safe scalar/array data before rollback.
 * Evidence is seeded directly at the ValidatedEvidence layer (as the existing T03
 * unit tests do); this does not modify T02/T03 behavior.
 */
final class CognitiveAffectiveEvaluationRunner
{
    public function __construct(
        private readonly LearningStateInferenceService $inference,
        private readonly IndicatorComparator $comparator,
        private readonly CognitiveAffectiveConstraintChecker $constraintChecker,
    ) {}

    /**
     * @param  list<CognitiveAffectiveScenario>  $scenarios
     * @return list<CognitiveAffectiveValidationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (CognitiveAffectiveScenario $s): CognitiveAffectiveValidationResult => $this->run($s), $scenarios);
    }

    public function run(CognitiveAffectiveScenario $scenario): CognitiveAffectiveValidationResult
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

        $dimensions['indicator_observability'] = ($constraintCheck['checks']['indicators_observable'] ?? false) ? 'pass' : 'fail';
        $dimensions['no_clinical_inference'] = ($constraintCheck['checks']['no_clinical_inference'] ?? false) ? 'pass' : 'fail';
        $dimensions['task_demand_separation'] = ($constraintCheck['checks']['bloom_dave_task_demand_only'] ?? false) ? 'pass' : 'fail';
        $dimensions['evidence_traceability'] = $provenanceCheck['traceable'] ? 'pass' : 'fail';
        $dimensions['determinism'] = $actual->deterministic ? 'pass' : 'fail';
        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['rule_compliance'] = $constraintCheck['compliant'] ? 'pass' : 'fail';
        $dimensions['failure_handling'] = $failureHandled ? 'review' : 'pass';

        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(fn (string $v): string => 'constraint: '.$v, $constraintCheck['violations']));
            $status = EvaluationStatus::Fail;
        }

        if (! $provenanceCheck['traceable']) {
            $differences[] = 'provenance: actual indicators are not fully traceable back to source evidence/events';
            $status = EvaluationStatus::Fail;
        }

        if (! $actual->deterministic) {
            $differences[] = 'determinism: repeated inference on the same evidence produced different indicators';
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new CognitiveAffectiveValidationResult(
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

    private function captureActual(CognitiveAffectiveScenario $scenario): ActualIndicators
    {
        DB::beginTransaction();

        try {
            [$student, $course, $activity] = $this->seedFixtures($scenario);

            foreach ($scenario->evidence as $spec) {
                $this->seedEvidence($student->id, $course->id, $activity->id, $spec);
            }

            $first = $this->inference->inferForLearnerActivity($student->id, $activity->id);
            $second = $this->inference->inferForLearnerActivity($student->id, $activity->id);

            $deterministic = $first->id === $second->id
                && $first->inference_key === $second->inference_key
                && $first->cognitive_indicator === $second->cognitive_indicator
                && $first->psychomotor_indicator === $second->psychomotor_indicator
                && $this->behavioral($first) === $this->behavioral($second)
                && LearningState::query()->count() === 1;

            $interventionCount = AdaptiveIntervention::query()->count();

            return $this->toActual($scenario, $first, $deterministic, $interventionCount);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function seedFixtures(CognitiveAffectiveScenario $scenario): array
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
                'concept' => $scenario->concept,
                'learning_objective' => 'Synthetic cognitive-affective objective for '.$scenario->concept.'.',
                'bloom_demand' => $scenario->bloomDemand,
                'dave_demand' => $scenario->daveDemand,
                'difficulty' => 'medium',
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
                'repeated_execution', 'execution_runtime_failure', 'execution_timeout' => 'code_run',
                default => $spec->evidenceType,
            },
            'payload' => ['synthetic' => true],
            'occurred_at' => now(),
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'evidence_category' => $spec->category->value,
            'evidence_type' => $spec->evidenceType,
            'observed_value' => ['summary' => $spec->evidenceType],
            'context_summary' => ['task_repetition' => 'new', 'task_difficulty' => 'medium', 'execution_anomaly' => 'none', 'network_environment' => 'unknown'],
            'quality' => $spec->quality->value,
            'confidence' => $spec->confidence->value,
            'validation_reason' => 'Synthetic validated evidence for M6-04 evaluation.',
            'validated_at' => now(),
        ]);
    }

    private function toActual(CognitiveAffectiveScenario $scenario, LearningState $state, bool $deterministic, int $interventionCount): ActualIndicators
    {
        $evidence = $state->validatedEvidence;

        return new ActualIndicators(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            state: $state->state->value,
            stateConfidence: $state->state_confidence->value,
            bloomDemand: $state->bloom_demand?->value,
            daveDemand: $state->dave_demand?->value,
            cognitiveIndicator: $state->cognitive_indicator,
            psychomotorIndicator: $state->psychomotor_indicator,
            behavioralIndicators: $this->behavioral($state),
            explanation: (string) $state->explanation,
            fusionSummary: is_array($state->fusion_summary) ? $state->fusion_summary : [],
            deterministic: $deterministic,
            interventionCountAfterInference: $interventionCount,
            provenance: [
                'scenario_id' => $scenario->scenarioId,
                'learning_state_id' => $state->id,
                'validated_evidence_ids' => $evidence->pluck('id')->sort()->values()->all(),
                'learning_event_ids' => $evidence->pluck('learning_event_id')->filter()->unique()->sort()->values()->all(),
                'inference_key' => $state->inference_key,
            ],
        );
    }

    private function errorOutcome(CognitiveAffectiveScenario $scenario, Throwable $e): ActualIndicators
    {
        return new ActualIndicators(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            state: 'error',
            stateConfidence: 'low',
            bloomDemand: $scenario->bloomDemand?->value,
            daveDemand: $scenario->daveDemand?->value,
            cognitiveIndicator: null,
            psychomotorIndicator: null,
            behavioralIndicators: [],
            explanation: '',
            fusionSummary: [],
            deterministic: false,
            interventionCountAfterInference: 0,
            provenance: ['scenario_id' => $scenario->scenarioId, 'error' => $e->getMessage()],
        );
    }

    /**
     * @return list<string>
     */
    private function behavioral(LearningState $state): array
    {
        return is_array($state->behavioral_indicators) ? array_values($state->behavioral_indicators) : [];
    }

    private function learnerRef(string $scenarioId): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-04|'.$scenarioId), 0, 12);
    }

    /**
     * @return array{scenario_id: string, has_learning_state: bool, evidence_present: bool, events_present: bool, chain_consistent: bool, links_back_to_scenario: bool, traceable: bool}
     */
    private function checkProvenance(CognitiveAffectiveScenario $scenario, ActualIndicators $actual): array
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
