<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Enums\WeakAreaClassification;
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
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\InterventionResponseQuery;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NextLearningActionService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Executes the real M4/M5 intervention & reassessment services against synthetic
 * M6-03 scenarios and captures the actual outcomes without persisting anything.
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION: every fixture and service write happens
 * inside a database transaction that is ALWAYS rolled back; the actual outcome is
 * captured into privacy-safe scalar/array data before rollback. The runner calls
 * only the authoritative service entry points and never mutates existing records
 * or production rules.
 */
final class InterventionEvaluationRunner
{
    private const CONCEPT = 'loops';

    public function __construct(
        private readonly LearningStateInferenceService $inference,
        private readonly AdaptiveInterventionService $interventionService,
        private readonly NextLearningActionService $nextActionService,
        private readonly AiAssistedReassessmentService $reassessmentService,
        private readonly InterventionResponseQuery $responseQuery,
        private readonly InterventionEvaluationConstraintChecker $constraintChecker,
    ) {}

    /**
     * @param  list<InterventionEvaluationScenario>  $scenarios
     * @return list<InterventionEvaluationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (InterventionEvaluationScenario $s): InterventionEvaluationResult => $this->run($s), $scenarios);
    }

    public function run(InterventionEvaluationScenario $scenario): InterventionEvaluationResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            [$actual, $comparison, $provenanceCheck] = $this->execute($scenario);
        } catch (Throwable $e) {
            $failureHandled = true;
            $actual = ['error' => $e->getMessage(), 'ml_or_llm_decision_maker' => false, 'scenario_id' => $scenario->scenarioId()];
            $comparison = ['status' => EvaluationStatus::Review, 'differences' => ['execution error: '.$e->getMessage()], 'dimensions' => []];
            $provenanceCheck = ['scenario_id' => $scenario->scenarioId(), 'traceable' => false, 'links_back_to_scenario' => true];
            $notes[] = 'scenario execution raised an exception and was contained: '.$e->getMessage();
        }

        $constraintCheck = $this->constraintChecker->check($scenario->kind(), $actual);

        $status = $comparison['status'];
        $dimensions = $comparison['dimensions'];
        $differences = $comparison['differences'];

        $dimensions['provenance'] = $provenanceCheck['traceable'] ? 'pass' : 'fail';
        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['rule_compliance'] = $constraintCheck['compliant'] ? 'pass' : 'fail';
        $dimensions['failure_handling'] = $failureHandled ? 'review' : 'pass';

        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(fn (string $v): string => 'constraint: '.$v, $constraintCheck['violations']));
            $status = EvaluationStatus::Fail;
        }

        if (! $provenanceCheck['traceable']) {
            $differences[] = 'provenance: actual outcome is not fully traceable back to source records';
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new InterventionEvaluationResult(
            scenarioId: $scenario->scenarioId(),
            kind: $scenario->kind(),
            category: $scenario->category(),
            status: $status,
            expected: $this->expectedArray($scenario),
            actual: $actual,
            differences: array_values($differences),
            dimensions: $dimensions,
            provenanceCheck: $provenanceCheck,
            constraintCheck: $constraintCheck,
            notes: $notes,
        );
    }

    /**
     * @return array{0: array<string, mixed>, 1: array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}, 2: array<string, mixed>}
     */
    private function execute(InterventionEvaluationScenario $scenario): array
    {
        DB::beginTransaction();

        try {
            return match ($scenario->kind()) {
                'intervention' => $this->runIntervention($scenario),
                'next_action' => $this->runNextAction($scenario),
                'reassessment' => $this->runReassessment($scenario),
                'response' => $this->runResponse($scenario),
                default => throw new \InvalidArgumentException('Unknown scenario kind: '.$scenario->kind()),
            };
        } finally {
            DB::rollBack();
        }
    }

    // ----- Intervention (T04) -------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}, 2: array<string, mixed>}
     */
    private function runIntervention(InterventionEvaluationScenario $scenario): array
    {
        /** @var InterventionScenario $scenario */
        [$student, , $activity] = $this->seedFixtures($scenario->concept, $scenario->bloomDemand, $scenario->daveDemand);

        foreach ($scenario->evidence as $spec) {
            $this->seedEvidence($student->id, $activity->learningUnit->module->course_id, $activity->id, $spec);
        }

        $state = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        $intervention = $this->interventionService->createForLearningState($state);
        $again = $this->interventionService->createForLearningState($state->fresh(['validatedEvidence', 'activity']));
        $idempotent = $intervention->id === $again->id && AdaptiveIntervention::query()->count() === 1;

        $metadata = is_array($intervention->metadata) ? $intervention->metadata : [];
        $actual = [
            'scenario_id' => $scenario->scenarioId(),
            'learner_ref' => $this->learnerRef($scenario->scenarioId()),
            'learning_state' => $state->state->value,
            'intervention_type' => $intervention->intervention_type?->value,
            'socratic_type' => $intervention->socratic_type?->value,
            'is_remedial' => (bool) $intervention->is_remedial,
            'is_strong' => (bool) $intervention->is_strong,
            'selection_rule' => $intervention->selection_rule,
            'provides_direct_answer' => (bool) ($metadata['provides_direct_answer'] ?? false),
            'idempotent' => $idempotent,
            'ml_or_llm_decision_maker' => false,
            'provenance' => [
                'scenario_id' => $scenario->scenarioId(),
                'learning_state_id' => $state->id,
                'adaptive_intervention_id' => $intervention->id,
                'validated_evidence_ids' => $state->validatedEvidence->pluck('id')->sort()->values()->all(),
            ],
        ];

        $comparison = $this->compareIntervention($scenario->expected, $actual);
        $provenanceCheck = $this->provenanceCheck($scenario->scenarioId(), [
            $actual['provenance']['learning_state_id'],
            $actual['provenance']['adaptive_intervention_id'],
        ], $actual['provenance']['scenario_id']);

        return [$actual, $comparison, $provenanceCheck];
    }

    /**
     * @param  array<string, mixed>  $actual
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    private function compareIntervention(ExpectedIntervention $expected, array $actual): array
    {
        $differences = [];
        $dimensions = [];

        // Intervention type.
        $typeDim = 'not_applicable';
        if ($expected->interventionType !== null) {
            if ($actual['intervention_type'] === $expected->interventionType->value) {
                $typeDim = 'pass';
            } elseif (in_array($actual['intervention_type'], array_map(fn (InterventionType $t): string => $t->value, $expected->acceptableTypes), true)) {
                $typeDim = 'review';
                $differences[] = 'intervention_type "'.$actual['intervention_type'].'" within acceptable set but not primary "'.$expected->interventionType->value.'"';
            } else {
                $typeDim = 'fail';
                $differences[] = 'intervention_type mismatch: expected "'.$expected->interventionType->value.'", actual "'.($actual['intervention_type'] ?? 'none').'"';
            }
        }
        $dimensions['intervention_type'] = $typeDim;

        // Remedial / strong.
        $dimensions['remedial'] = $this->boolDim('is_remedial', $expected->expectRemedial, (bool) $actual['is_remedial'], $differences);
        $dimensions['strong'] = $this->boolDim('is_strong', $expected->expectStrong, (bool) $actual['is_strong'], $differences);

        // Socratic presence.
        $socraticPresent = $actual['socratic_type'] !== null;
        $dimensions['socratic'] = $this->boolDim('socratic_present', $expected->expectSocratic, $socraticPresent, $differences);

        // Selection rule (optional exact).
        if ($expected->selectionRule !== null) {
            if ($actual['selection_rule'] === $expected->selectionRule) {
                $dimensions['selection_rule'] = 'pass';
            } else {
                $dimensions['selection_rule'] = 'fail';
                $differences[] = 'selection_rule mismatch: expected "'.$expected->selectionRule.'", actual "'.$actual['selection_rule'].'"';
            }
        }

        $dimensions['idempotency'] = $actual['idempotent'] ? 'pass' : 'fail';
        if (! $actual['idempotent']) {
            $differences[] = 'idempotency: repeated T04 selection produced a different or duplicated intervention';
        }

        return $this->finalize($expected->ambiguous, $dimensions, $differences);
    }

    // ----- Next action (T05) --------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}, 2: array<string, mixed>}
     */
    private function runNextAction(InterventionEvaluationScenario $scenario): array
    {
        /** @var NextActionScenario $scenario */
        [$student, , $activity] = $this->seedFixtures($scenario->concept, $scenario->bloomDemand, $scenario->daveDemand);

        foreach ($scenario->evidence as $spec) {
            $this->seedEvidence($student->id, $activity->learningUnit->module->course_id, $activity->id, $spec);
        }

        $state = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        $intervention = $scenario->createIntervention ? $this->interventionService->createForLearningState($state) : null;
        $action = $this->nextActionService->decideForLearningState($state, $intervention, $scenario->retryOutcome);

        $actual = [
            'scenario_id' => $scenario->scenarioId(),
            'learner_ref' => $this->learnerRef($scenario->scenarioId()),
            'learning_state' => $state->state->value,
            'next_action' => $action->action->value,
            'retry_outcome' => $action->retry_outcome,
            'has_intervention' => $intervention !== null,
            'ml_or_llm_decision_maker' => false,
            'provenance' => [
                'scenario_id' => $scenario->scenarioId(),
                'learning_state_id' => $state->id,
                'next_learning_action_id' => $action->id,
                'adaptive_intervention_id' => $intervention?->id,
            ],
        ];

        $comparison = $this->compareNextAction($scenario->expected, $actual);
        $provenanceCheck = $this->provenanceCheck($scenario->scenarioId(), [
            $actual['provenance']['learning_state_id'],
            $actual['provenance']['next_learning_action_id'],
        ], $actual['provenance']['scenario_id']);

        return [$actual, $comparison, $provenanceCheck];
    }

    /**
     * @param  array<string, mixed>  $actual
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    private function compareNextAction(ExpectedNextAction $expected, array $actual): array
    {
        $differences = [];
        $dimensions = [];

        if ($actual['next_action'] === $expected->action->value) {
            $dimensions['next_action'] = 'pass';
        } elseif (in_array($actual['next_action'], array_map(fn ($a): string => $a->value, $expected->acceptableActions), true)) {
            $dimensions['next_action'] = 'review';
            $differences[] = 'next_action "'.$actual['next_action'].'" within acceptable set but not primary "'.$expected->action->value.'"';
        } else {
            $dimensions['next_action'] = 'fail';
            $differences[] = 'next_action mismatch: expected "'.$expected->action->value.'", actual "'.$actual['next_action'].'"';
        }

        return $this->finalize($expected->ambiguous, $dimensions, $differences);
    }

    // ----- Reassessment (M5-04) ----------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}, 2: array<string, mixed>}
     */
    private function runReassessment(InterventionEvaluationScenario $scenario): array
    {
        /** @var ReassessmentScenario $scenario */
        [$student, $course, $activity] = $this->seedFixtures($scenario->concept, BloomLevel::Apply, DaveLevel::Manipulation, sentinelIdentity: true);

        if ($scenario->useRealWeakAreaQuery) {
            $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);
            $result = $this->reassessmentService->createCandidateForLearningArea($student->id, $course->id, 'concept:'.$scenario->concept);
        } else {
            $finding = $this->syntheticFinding($scenario, $student->id, $course->id, $activity->id);
            $result = $this->reassessmentService->createCandidateFromFinding($finding);
        }

        $spec = is_array($result['specification'] ?? null) ? $result['specification'] : [];
        $candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : null;
        $provenance = is_array($result['provenance'] ?? null) ? $result['provenance'] : [];

        $actual = [
            'scenario_id' => $scenario->scenarioId(),
            'learner_ref' => $this->learnerRef($scenario->scenarioId()),
            'research_learner_id' => $result['research_learner_id'] ?? null,
            'eligible' => (bool) ($result['eligible'] ?? false),
            'status' => $result['status'] ?? null,
            'spec_concept' => $spec['concept'] ?? null,
            'spec_bloom_demand' => $spec['bloom_demand'] ?? null,
            'spec_dave_demand' => $spec['dave_demand'] ?? null,
            'spec_bloom_semantics' => $spec['bloom_semantics'] ?? null,
            'candidate_present' => $candidate !== null,
            'includes_direct_answer' => $candidate['includes_direct_answer'] ?? null,
            'source_of_truth_unchanged' => $result['source_of_truth_unchanged'] ?? true,
            'claims_improvement' => (bool) ($result['claims_improvement'] ?? false),
            'claims_effectiveness' => (bool) ($result['claims_effectiveness'] ?? false),
            'ml_or_llm_decision_maker' => false,
            'provenance' => [
                'scenario_id' => $scenario->scenarioId(),
                'learning_state_ids' => array_values($provenance['learning_state_ids'] ?? []),
                'validated_evidence_ids' => array_values($provenance['validated_evidence_ids'] ?? []),
                'activity_ids' => array_values($provenance['activity_ids'] ?? []),
            ],
        ];

        $comparison = $this->compareReassessment($scenario, $actual);

        // Provenance is required only when a candidate is eligible/generated.
        if ($actual['eligible']) {
            $traceable = $actual['provenance']['learning_state_ids'] !== []
                && $actual['provenance']['validated_evidence_ids'] !== [];
            $provenanceCheck = [
                'scenario_id' => $scenario->scenarioId(),
                'learning_state_ids' => $actual['provenance']['learning_state_ids'],
                'validated_evidence_ids' => $actual['provenance']['validated_evidence_ids'],
                'links_back_to_scenario' => true,
                'traceable' => $traceable,
            ];
        } else {
            $provenanceCheck = [
                'scenario_id' => $scenario->scenarioId(),
                'links_back_to_scenario' => true,
                'traceable' => true,
            ];
        }

        return [$actual, $comparison, $provenanceCheck];
    }

    /**
     * @param  array<string, mixed>  $actual
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    private function compareReassessment(ReassessmentScenario $scenario, array $actual): array
    {
        $expected = $scenario->expected;
        $differences = [];
        $dimensions = [];

        $dimensions['eligibility'] = $this->boolDim('eligible', $expected->expectEligible, (bool) $actual['eligible'], $differences);

        $acceptable = array_map(fn ($s): string => $s->value, $expected->acceptableStatuses);
        if (in_array($actual['status'], $acceptable, true)) {
            $dimensions['status'] = 'pass';
        } else {
            $dimensions['status'] = 'fail';
            $differences[] = 'reassessment status "'.($actual['status'] ?? 'none').'" not in acceptable set ['.implode(', ', $acceptable).']';
        }

        if ($expected->expectConceptAlignment) {
            $aligned = $actual['spec_concept'] === $scenario->concept
                && $actual['spec_bloom_semantics'] === 'task_demand'
                && $actual['spec_bloom_demand'] !== null
                && $actual['spec_dave_demand'] !== null;
            $dimensions['spec_alignment'] = $aligned ? 'pass' : 'fail';
            if (! $aligned) {
                $differences[] = 'reassessment specification not aligned to task demand (concept/bloom/dave)';
            }
        }

        if ($expected->expectCandidateContent) {
            $dimensions['candidate_content'] = $actual['candidate_present'] ? 'pass' : 'fail';
            if (! $actual['candidate_present']) {
                $differences[] = 'expected a generated candidate, but none was present';
            }
        }

        return $this->finalize($expected->ambiguous, $dimensions, $differences);
    }

    // ----- Response (M5-05) ---------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}, 2: array<string, mixed>}
     */
    private function runResponse(InterventionEvaluationScenario $scenario): array
    {
        /** @var ResponseScenario $scenario */
        [$student, $course, $activity] = $this->seedFixtures($scenario->concept ?? self::CONCEPT, BloomLevel::Apply, DaveLevel::Manipulation);

        $beforeState = $this->makeState($student->id, $activity->id, $scenario->beforeState, now()->subMinutes(40), true, 'submission_rejected', 'unresolved_performance_outcome_observed', $course->id);
        $intervention = $this->makeIntervention($student->id, $activity->id, $beforeState, now()->subMinutes(30), $scenario->remedial);

        if ($scenario->afterState !== null) {
            $this->makeState($student->id, $activity->id, $scenario->afterState, now()->subMinutes(10), true, $scenario->postEvidenceType ?? 'submission_accepted', $scenario->afterCognitive, $course->id);
        }

        $result = $this->responseQuery->forIntervention($intervention->fresh(['learningState', 'activity', 'nextLearningActions']));

        $interpretation = $result['research_interpretation'];
        $observed = $result['observed_outcome'];
        $temporal = $result['temporal_window'];
        $provenance = $result['provenance'];

        $actual = [
            'scenario_id' => $scenario->scenarioId(),
            'learner_ref' => $this->learnerRef($scenario->scenarioId()),
            'response_classification' => $interpretation['response_classification'],
            'observed_improvement_signal' => $interpretation['observed_improvement_signal'],
            'observed_improvement' => (bool) $interpretation['observed_improvement'],
            'before_state' => $observed['before_state'],
            'after_state' => $observed['after_state'],
            'state_transition_type' => $observed['state_transition_type'],
            'claims_causal_effectiveness' => (bool) $interpretation['claims_causal_effectiveness'],
            'claims_intervention_caused_improvement' => (bool) $interpretation['claims_intervention_caused_improvement'],
            'claims_treatment_effect' => (bool) $interpretation['claims_treatment_effect'],
            'delivery_timestamp_available' => (bool) $temporal['delivery_timestamp_available'],
            'temporal_ordering' => $temporal['ordering'],
            'intervention_available_at' => $temporal['intervention_available_at'],
            'after_state_inferred_at' => $temporal['after_state_inferred_at'],
            'ml_or_llm_decision_maker' => false,
            'provenance' => [
                'scenario_id' => $scenario->scenarioId(),
                'adaptive_intervention_id' => $provenance['adaptive_intervention_id'],
                'before_learning_state_id' => $provenance['before_learning_state_id'],
                'after_learning_state_id' => $provenance['after_learning_state_id'],
                'post_validated_evidence_ids' => array_values($provenance['post_validated_evidence_ids'] ?? []),
            ],
        ];

        $comparison = $this->compareResponse($scenario->expected, $actual);

        $traceable = $actual['provenance']['adaptive_intervention_id'] !== null
            && $actual['provenance']['before_learning_state_id'] !== null
            && $actual['delivery_timestamp_available'] === false;
        $provenanceCheck = [
            'scenario_id' => $scenario->scenarioId(),
            'adaptive_intervention_id' => $actual['provenance']['adaptive_intervention_id'],
            'before_learning_state_id' => $actual['provenance']['before_learning_state_id'],
            'after_learning_state_id' => $actual['provenance']['after_learning_state_id'],
            'links_back_to_scenario' => true,
            'delivery_timestamp_invented' => $actual['delivery_timestamp_available'],
            'traceable' => $traceable,
        ];

        return [$actual, $comparison, $provenanceCheck];
    }

    /**
     * @param  array<string, mixed>  $actual
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    private function compareResponse(ExpectedResponse $expected, array $actual): array
    {
        $differences = [];
        $dimensions = [];

        if ($actual['response_classification'] === $expected->classification->value) {
            $dimensions['response_classification'] = 'pass';
        } elseif (in_array($actual['response_classification'], array_map(fn ($c): string => $c->value, $expected->acceptableClassifications), true)) {
            $dimensions['response_classification'] = 'review';
            $differences[] = 'response_classification "'.$actual['response_classification'].'" within acceptable set but not primary "'.$expected->classification->value.'"';
        } else {
            $dimensions['response_classification'] = 'fail';
            $differences[] = 'response_classification mismatch: expected "'.$expected->classification->value.'", actual "'.$actual['response_classification'].'"';
        }

        if ($actual['observed_improvement_signal'] === $expected->improvementSignal->value) {
            $dimensions['improvement_signal'] = 'pass';
        } else {
            $dimensions['improvement_signal'] = 'fail';
            $differences[] = 'observed_improvement_signal mismatch: expected "'.$expected->improvementSignal->value.'", actual "'.$actual['observed_improvement_signal'].'"';
        }

        $dimensions['observed_improvement'] = $this->boolDim('observed_improvement', $expected->observedImprovement, (bool) $actual['observed_improvement'], $differences);

        // Temporal semantics: never invent a delivery timestamp.
        $dimensions['temporal'] = $actual['delivery_timestamp_available'] === false ? 'pass' : 'fail';
        if ($actual['delivery_timestamp_available'] !== false) {
            $differences[] = 'temporal: a delivery timestamp was reported where none should exist';
        }

        return $this->finalize($expected->ambiguous, $dimensions, $differences);
    }

    // ----- Shared helpers -----------------------------------------------------

    /**
     * @param  list<string>  $differences
     */
    private function boolDim(string $label, bool $expected, bool $actual, array &$differences): string
    {
        if ($expected === $actual) {
            return 'pass';
        }

        $differences[] = $label.' mismatch: expected '.($expected ? 'true' : 'false').', actual '.($actual ? 'true' : 'false');

        return 'fail';
    }

    /**
     * @param  array<string, string>  $dimensions
     * @param  list<string>  $differences
     * @return array{status: EvaluationStatus, differences: list<string>, dimensions: array<string, string>}
     */
    private function finalize(bool $ambiguous, array $dimensions, array $differences): array
    {
        if (in_array('fail', $dimensions, true)) {
            $status = EvaluationStatus::Fail;
        } elseif ($ambiguous || in_array('review', $dimensions, true)) {
            $status = EvaluationStatus::Review;
        } else {
            $status = EvaluationStatus::Pass;
        }

        return ['status' => $status, 'differences' => array_values($differences), 'dimensions' => $dimensions];
    }

    /**
     * @param  list<int|null>  $requiredIds
     * @return array{scenario_id: string, required_ids: list<int|null>, links_back_to_scenario: bool, traceable: bool}
     */
    private function provenanceCheck(string $scenarioId, array $requiredIds, string $embeddedScenarioId): array
    {
        $traceable = $embeddedScenarioId === $scenarioId
            && ! in_array(null, $requiredIds, true);

        return [
            'scenario_id' => $scenarioId,
            'required_ids' => $requiredIds,
            'links_back_to_scenario' => $embeddedScenarioId === $scenarioId,
            'traceable' => $traceable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedArray(InterventionEvaluationScenario $scenario): array
    {
        return match (true) {
            $scenario instanceof InterventionScenario => $scenario->expected->toArray(),
            $scenario instanceof NextActionScenario => $scenario->expected->toArray(),
            $scenario instanceof ReassessmentScenario => $scenario->expected->toArray(),
            $scenario instanceof ResponseScenario => $scenario->expected->toArray(),
            default => [],
        };
    }

    private function learnerRef(string $scenarioId): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-03|'.$scenarioId), 0, 12);
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function seedFixtures(string $concept, ?BloomLevel $bloom, ?DaveLevel $dave, bool $sentinelIdentity = false): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create($sentinelIdentity ? [
            'name' => 'Secret Learner Sentinel',
            'email' => 'sentinel.learner@example.com',
        ] : []);
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'title' => 'Synthetic '.$concept.' drill',
            'concept' => $concept,
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => $bloom,
            'dave_demand' => $dave,
            'difficulty' => 'medium',
        ]);

        return [$student, $course, $activity->fresh(['learningUnit.module'])];
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
            'validation_reason' => 'Synthetic validated evidence for M6-03 evaluation.',
            'validated_at' => now(),
        ]);
    }

    private function seedPersistentWeakArea(int $userId, int $courseId, int $activityId): void
    {
        foreach ([40, 30, 20] as $minutes) {
            $state = LearningState::factory()->create([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'state' => LearningStateValue::NeedsSupport,
                'state_confidence' => StateConfidence::Medium,
                'inferred_at' => now()->subMinutes($minutes),
                'inference_key' => hash('sha256', uniqid('m6-03-persist-'.$minutes, true)),
                'cognitive_indicator' => 'unresolved_performance_outcome_observed',
                'behavioral_indicators' => ['persistent_attempt_behavior'],
                'bloom_demand' => BloomLevel::Apply,
                'dave_demand' => DaveLevel::Manipulation,
                'explanation' => 'Synthetic needs_support.',
                'inference_rule' => 'fixture',
            ]);
            $evidence = $this->makeEvidence($userId, $courseId, $activityId, 'submission_rejected', now()->subMinutes($minutes));
            $state->validatedEvidence()->sync([$evidence->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function syntheticFinding(ReassessmentScenario $scenario, int $userId, int $courseId, int $activityId): array
    {
        $state = LearningState::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'state' => $scenario->classification === WeakAreaClassification::NoCurrentWeakness
                ? LearningStateValue::Stable
                : LearningStateValue::NeedsSupport,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => now()->subMinutes(10),
            'inference_key' => hash('sha256', uniqid('m6-03-finding', true)),
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $evidence = $this->makeEvidence($userId, $courseId, $activityId, 'submission_rejected', now()->subMinutes(10));
        $state->validatedEvidence()->sync([$evidence->id]);

        return [
            'research_learner_id' => hash('sha256', 'm6-03-eval'),
            'learner_id' => $userId,
            'course_id' => $courseId,
            'learning_area_key' => 'concept:'.$scenario->concept,
            'learning_area_label' => $scenario->concept,
            'learning_area_representation' => 'activity_concept',
            'classification' => $scenario->classification->value,
            'is_weak_area' => in_array($scenario->classification, [
                WeakAreaClassification::WeakPersistent,
                WeakAreaClassification::WeakRepeatedFailure,
                WeakAreaClassification::WeakUnresolved,
            ], true),
            'supporting_evidence_ids' => [$evidence->id],
            'supporting_learning_state_ids' => [$state->id],
            'activity_ids' => [$activityId],
            'trajectory' => ['sequence' => ['needs_support', 'needs_support'], 'transitions' => []],
            'bloom_demand_context' => ['apply'],
            'dave_demand_context' => ['manipulation'],
            'evidence_quality_summary' => ['valid' => 1],
            'evidence_confidence_summary' => ['high' => 1],
            'detection_rule' => 'fixture',
            'explanation' => 'Synthetic weak-area finding for M6-03 evaluation.',
        ];
    }

    private function makeState(
        int $userId,
        int $activityId,
        LearningStateValue $state,
        \DateTimeInterface $inferredAt,
        bool $withEvidence,
        ?string $evidenceType,
        ?string $cognitive,
        int $courseId,
    ): LearningState {
        $record = LearningState::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid($state->value, true)),
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => null,
            'behavioral_indicators' => ['persistent_attempt_behavior'],
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Synthetic state for M6-03 response evaluation.',
            'inference_rule' => 'fixture_m6_03',
        ]);

        if ($withEvidence && $evidenceType !== null) {
            $evidence = $this->makeEvidence($userId, $courseId, $activityId, $evidenceType, $inferredAt);
            $record->validatedEvidence()->sync([$evidence->id]);
        }

        return $record->fresh(['validatedEvidence.learningEvent']);
    }

    private function makeIntervention(int $userId, int $activityId, LearningState $state, \DateTimeInterface $createdAt, bool $remedial): AdaptiveIntervention
    {
        $evidenceIds = $state->validatedEvidence->pluck('id')->values()->all();

        $intervention = AdaptiveIntervention::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_state_id' => $state->id,
            'intervention_type' => InterventionType::GuidedRetry,
            'is_remedial' => $remedial,
            'is_strong' => $remedial,
            'selection_rule' => 'fixture_m6_03',
            'reason' => 'Synthetic intervention for M6-03 response evaluation.',
            'content' => 'Try again with guidance.',
            'intervention_key' => hash('sha256', uniqid('m6-03-intervention', true)),
            'metadata' => ['validated_evidence_ids' => $evidenceIds, 'provides_direct_answer' => false],
        ]);

        $intervention->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $intervention->fresh();
    }

    private function makeEvidence(int $userId, int $courseId, int $activityId, string $type, \DateTimeInterface $at): ValidatedEvidence
    {
        $event = LearningEvent::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => $type,
            'payload' => ['synthetic' => true],
            'occurred_at' => $at,
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::Performance->value,
            'evidence_type' => $type,
            'observed_value' => ['summary' => $type],
            'context_summary' => [],
            'quality' => EvidenceQuality::Valid->value,
            'confidence' => EvidenceConfidence::High->value,
            'validation_reason' => 'Synthetic M6-03 evidence.',
            'validated_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
