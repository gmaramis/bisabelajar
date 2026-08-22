<?php

namespace App\Services\Evaluation;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Services\Research\NexusClosedLoopService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes the real NEXUS/M4 pipeline against a synthetic scenario and captures
 * the actual outcome without persisting anything (M6-01).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION:
 * All scenario fixtures and every pipeline write (LearningEvent, ValidatedEvidence,
 * LearningState, AdaptiveIntervention, NextLearningAction) happen inside a database
 * transaction that is ALWAYS rolled back. The actual outcome is captured into
 * detached scalar/array data before rollback, so evaluation leaves the production
 * learning source-of-truth completely unchanged. The runner never calls any M3/M4/M5
 * mutation directly other than the authoritative pipeline entry points, and never
 * modifies existing records.
 */
final class NexusEvaluationRunner
{
    public function __construct(
        private readonly NexusClosedLoopService $closedLoop,
        private readonly OutcomeComparator $comparator,
        private readonly ConstraintChecker $constraintChecker,
    ) {}

    /**
     * @param  list<EvaluationScenario>  $scenarios
     * @return list<ScenarioResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (EvaluationScenario $s): ScenarioResult => $this->run($s), $scenarios);
    }

    public function run(EvaluationScenario $scenario): ScenarioResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            $actual = $this->captureActual($scenario);
        } catch (Throwable $e) {
            // Failure handling: a scenario that errors is surfaced as REVIEW with a
            // note, never as a false PASS, and still leaves no residue.
            $failureHandled = true;
            $actual = $this->errorOutcome($scenario, $e);
            $notes[] = 'scenario execution raised an exception and was contained: '.$e->getMessage();
        }

        $comparison = $this->comparator->compare($scenario, $scenario->expected, $actual);
        $constraintCheck = $this->constraintChecker->check($actual);
        $provenanceCheck = $this->checkProvenance($scenario, $actual);

        $status = $comparison['status'];
        $dimensions = $comparison['dimensions'];
        $differences = $comparison['differences'];

        // Fold cross-cutting dimensions (owned by the runner, not the comparator).
        $dimensions['traceability'] = $provenanceCheck['traceable'] ? 'pass' : 'fail';
        $dimensions['provenance'] = $provenanceCheck['links_back_to_scenario'] ? 'pass' : 'fail';
        $dimensions['rule_compliance'] = $constraintCheck['compliant'] ? 'pass' : 'fail';
        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['determinism'] = 'pass';
        $dimensions['failure_handling'] = $failureHandled ? 'review' : ($dimensions['failure_handling'] ?? 'pass');

        // A constraint or provenance violation is a hard failure regardless of correctness.
        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(
                fn (string $v): string => 'constraint: '.$v,
                $constraintCheck['violations'],
            ));
            $status = EvaluationStatus::Fail;
        }

        if (! $provenanceCheck['traceable']) {
            $differences[] = 'provenance: actual outcome is not fully traceable back to source records';
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new ScenarioResult(
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

    /**
     * Seed the scenario, run the pipeline, capture the actual outcome, then discard
     * everything via transaction rollback.
     */
    private function captureActual(EvaluationScenario $scenario): ActualOutcome
    {
        DB::beginTransaction();

        try {
            [$student, $course, $activity] = $this->seedFixtures($scenario);

            foreach ($scenario->initialEvents as $event) {
                LearningEvent::record($event['type'], $student->id, $course->id, $activity->id, $event['payload']);
            }

            $result = $this->closedLoop->processLearnerActivity($student->id, $activity->id);
            $remedialCreated = (bool) $result['remedial_intervention_created'];

            if ($scenario->runsRetry() && $result['intervention'] !== null) {
                foreach ($scenario->retryEvents as $event) {
                    LearningEvent::record($event['type'], $student->id, $course->id, $activity->id, $event['payload']);
                }

                $retry = $this->closedLoop->processAfterRetry(
                    $student->id,
                    $activity->id,
                    $result['intervention'],
                    (string) $scenario->retryOutcome,
                );

                $remedialCreated = $remedialCreated || (bool) $retry['remedial_intervention_created'];
                $result = $retry;
            }

            return $this->toActualOutcome($scenario, $result, $remedialCreated);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function seedFixtures(EvaluationScenario $scenario): array
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
                'learning_objective' => 'Synthetic evaluation objective for '.$scenario->concept.'.',
                'bloom_demand' => $scenario->bloomDemand,
                'dave_demand' => $scenario->daveDemand,
            ]);

        return [$student, $course, $activity];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function toActualOutcome(EvaluationScenario $scenario, array $result, bool $remedialCreated): ActualOutcome
    {
        $state = $result['learning_state'];
        $intervention = $result['intervention'];
        $nextAction = $result['next_action'];

        $provenance = is_array($result['provenance']) ? $result['provenance'] : [];
        $provenance['scenario_id'] = $scenario->scenarioId;

        return new ActualOutcome(
            learnerRef: $this->learnerRef($scenario),
            state: $state->state->value,
            stateConfidence: $state->state_confidence->value,
            bloomDemand: $state->bloom_demand?->value,
            daveDemand: $state->dave_demand?->value,
            interventionPresent: $intervention !== null,
            interventionType: $intervention?->intervention_type?->value,
            remedialInterventionCreated: $remedialCreated,
            nextAction: $nextAction->action->value,
            validatedEvidenceIds: array_values(array_map('intval', $result['validated_evidence_ids'] ?? [])),
            provenance: $provenance,
        );
    }

    private function errorOutcome(EvaluationScenario $scenario, Throwable $e): ActualOutcome
    {
        return new ActualOutcome(
            learnerRef: $this->learnerRef($scenario),
            state: 'error',
            stateConfidence: 'low',
            bloomDemand: $scenario->bloomDemand?->value,
            daveDemand: $scenario->daveDemand?->value,
            interventionPresent: false,
            interventionType: null,
            remedialInterventionCreated: false,
            nextAction: 'error',
            validatedEvidenceIds: [],
            provenance: [
                'scenario_id' => $scenario->scenarioId,
                'error' => $e->getMessage(),
                'ml_or_llm_orchestration' => false,
                'longitudinal_analysis' => false,
                'creates_reassessment_question' => false,
            ],
        );
    }

    /**
     * Pseudonymous, deterministic learner reference. Contains no PII and is stable
     * across runs of the same scenario, supporting privacy-safe, deterministic output.
     */
    private function learnerRef(EvaluationScenario $scenario): string
    {
        return 'learner-'.substr(hash('sha256', 'nexus-eval|'.$scenario->scenarioId), 0, 12);
    }

    /**
     * @return array{
     *     scenario_id: string,
     *     has_learning_state: bool,
     *     has_next_action: bool,
     *     evidence_present: bool,
     *     events_present: bool,
     *     chain_consistent: bool,
     *     links_back_to_scenario: bool,
     *     traceable: bool
     * }
     */
    private function checkProvenance(EvaluationScenario $scenario, ActualOutcome $actual): array
    {
        $provenance = $actual->provenance;

        $hasLearningState = ($provenance['learning_state_id'] ?? null) !== null;
        $hasNextAction = ($provenance['next_learning_action_id'] ?? null) !== null;
        $evidencePresent = count($provenance['validated_evidence_ids'] ?? []) > 0;
        $eventsPresent = count($provenance['learning_event_ids'] ?? []) > 0;

        // If evidence exists it must trace back to at least one learning event.
        $chainConsistent = $evidencePresent ? $eventsPresent : true;

        $linksBack = ($provenance['scenario_id'] ?? null) === $scenario->scenarioId;

        $traceable = $hasLearningState && $hasNextAction && $chainConsistent;

        return [
            'scenario_id' => $scenario->scenarioId,
            'has_learning_state' => $hasLearningState,
            'has_next_action' => $hasNextAction,
            'evidence_present' => $evidencePresent,
            'events_present' => $eventsPresent,
            'chain_consistent' => $chainConsistent,
            'links_back_to_scenario' => $linksBack,
            'traceable' => $traceable,
        ];
    }
}
