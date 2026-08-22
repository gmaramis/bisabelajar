<?php

namespace Tests\Feature\Evaluation;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\NextLearningAction;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Evaluation\ConstraintChecker;
use App\Services\Evaluation\EvaluationReport;
use App\Services\Evaluation\EvaluationScenario;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\ExpectedOutcome;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\OutcomeComparator;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\EvidenceValidationService;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NextLearningActionService;
use App\Services\Research\NexusClosedLoopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-01 — NEXUS Evaluation Framework.
 *
 * Verifies the evaluation overlay: Scenario → Expected → NEXUS → Actual → Compare
 * → PASS/FAIL/REVIEW, with independence, read-only protection, provenance, privacy,
 * determinism, and M3/M4/M5 regression compatibility.
 */
class NexusEvaluationFrameworkTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): NexusEvaluationRunner
    {
        return app(NexusEvaluationRunner::class);
    }

    private function scenario(string $id): EvaluationScenario
    {
        foreach ((new ScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found in library.");
    }

    // 1. PASS scenario.
    public function test_pass_scenario_reports_pass(): void
    {
        $result = $this->runner()->run($this->scenario('LS-STABLE-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::Stable->value, $result->actual->state);
        $this->assertSame(NextLearningActionType::Continue->value, $result->actual->nextAction);
        $this->assertFalse($result->actual->remedialInterventionCreated);
        $this->assertSame([], $result->differences);
        $this->assertSame('pass', $result->dimensions['correctness']);
    }

    // 2. FAIL scenario.
    public function test_fail_scenario_reports_fail_with_differences(): void
    {
        $result = $this->runner()->run($this->scenario('LS-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        // NEXUS actually produced needs_support, contradicting the authored "stable".
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->state);
        $this->assertSame('fail', $result->dimensions['correctness']);
    }

    // 3. REVIEW / ambiguous scenario.
    public function test_review_scenario_reports_review(): void
    {
        $result = $this->runner()->run($this->scenario('BD-UNCERTAIN-ONLY-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertTrue($result->expected->ambiguous);
        // A single timed-out run yields only uncertain evidence → insufficient_evidence.
        $this->assertSame(LearningStateValue::InsufficientEvidence->value, $result->actual->state);
    }

    // 4. Expected outcome independence.
    public function test_expected_outcomes_are_independent_of_the_implementation_under_test(): void
    {
        // Static: the scenario library must not reference any production NEXUS service.
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/ScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);
        $this->assertStringNotContainsString('LearningStateInferenceService', $code);
        $this->assertStringNotContainsString('AdaptiveInterventionService', $code);
        $this->assertStringNotContainsString('NextLearningActionService', $code);
        $this->assertStringNotContainsString('EvidenceValidationService', $code);

        // Behavioral: expected outcomes are authored literals, available without any
        // pipeline execution or database access.
        $expected = $this->scenario('LS-STABLE-001')->expected;
        $this->assertInstanceOf(ExpectedOutcome::class, $expected);
        $this->assertSame(LearningStateValue::Stable, $expected->state);
        $this->assertNotSame('', $expected->rationale);
        $this->assertSame(0, LearningState::query()->count());

        // The FAIL scenario proves Expected is not derived from Actual: if it were, the
        // two could never disagree.
        $failResult = $this->runner()->run($this->scenario('LS-DIVERGENCE-FAIL-001'));
        $this->assertNotSame($failResult->expected->state->value, $failResult->actual->state);
    }

    // 5. Read-only / source-of-truth protection.
    public function test_evaluation_does_not_mutate_production_source_of_truth(): void
    {
        // Establish real production learning records via the authoritative pipeline.
        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, [
            'status' => 'success', 'passes_evaluation' => true,
        ]);
        $production = app(NexusClosedLoopService::class)->processLearnerActivity($student->id, $activity->id);
        $productionStateId = $production['learning_state']->id;
        $productionStateValue = $production['learning_state']->state->value;

        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        // Run the entire evaluation library (which internally exercises the pipeline).
        $results = $this->runner()->runMany((new ScenarioLibrary)->all());
        $this->assertNotEmpty($results);

        $after = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        $this->assertSame($before, $after, 'Evaluation must not add or remove any production learning records.');

        // The pre-existing production record is untouched.
        $reloaded = LearningState::query()->find($productionStateId);
        $this->assertNotNull($reloaded);
        $this->assertSame($productionStateValue, $reloaded->state->value);
        $this->assertSame(1, LearningState::query()->count());
    }

    // 6. Provenance.
    public function test_result_preserves_provenance_back_to_scenario_and_source_records(): void
    {
        $result = $this->runner()->run($this->scenario('IV-NEEDS-SUPPORT-001'));

        $this->assertTrue($result->provenanceCheck['traceable']);
        $this->assertTrue($result->provenanceCheck['links_back_to_scenario']);
        $this->assertTrue($result->provenanceCheck['has_learning_state']);
        $this->assertTrue($result->provenanceCheck['has_next_action']);
        $this->assertSame('IV-NEEDS-SUPPORT-001', $result->actual->provenance['scenario_id']);
        $this->assertNotEmpty($result->actual->provenance['learning_event_ids']);
        $this->assertNotEmpty($result->actual->provenance['validated_evidence_ids']);
        $this->assertSame('pass', $result->dimensions['traceability']);
        $this->assertSame('pass', $result->dimensions['provenance']);
    }

    // 7. Deterministic ordering / results.
    public function test_evaluation_is_deterministic_across_runs(): void
    {
        $first = $this->runner()->run($this->scenario('CL-PROGRESSING-001'));
        $second = $this->runner()->run($this->scenario('CL-PROGRESSING-001'));

        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->differences, $second->differences);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual->state, $second->actual->state);
        $this->assertSame($first->actual->nextAction, $second->actual->nextAction);
        $this->assertSame($first->actual->remedialInterventionCreated, $second->actual->remedialInterventionCreated);
        $this->assertSame($first->actual->learnerRef, $second->actual->learnerRef);

        // Report ordering is stable and independent of input order.
        $scenarios = (new ScenarioLibrary)->all();
        $forward = new EvaluationReport($this->runner()->runMany($scenarios));
        $reversed = new EvaluationReport($this->runner()->runMany(array_reverse($scenarios)));
        $this->assertSame(
            array_column($forward->toArray()['scenarios'], 'scenario_id'),
            array_column($reversed->toArray()['scenarios'], 'scenario_id'),
        );
    }

    // 8. Multiple scenarios.
    public function test_report_aggregates_multiple_scenarios(): void
    {
        $results = $this->runner()->runMany((new ScenarioLibrary)->all());
        $report = new EvaluationReport($results);
        $summary = $report->summary();

        $this->assertSame(6, $summary['total']);
        $this->assertSame($summary['total'], $summary['pass'] + $summary['fail'] + $summary['review']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertSame(4, $summary['pass']);

        $ids = array_column($report->toArray()['scenarios'], 'scenario_id');
        $this->assertContains('LS-STABLE-001', $ids);
        $this->assertContains('CL-PROGRESSING-001', $ids);
        $this->assertContains('BD-UNCERTAIN-ONLY-001', $ids);
    }

    // 9. Privacy-safe evaluation output.
    public function test_evaluation_output_is_privacy_safe(): void
    {
        $results = $this->runner()->runMany((new ScenarioLibrary)->all());

        foreach ($results as $result) {
            $this->assertTrue($result->constraintCheck['checks']['privacy_safe']);
            $this->assertSame('pass', $result->dimensions['privacy']);
            $this->assertStringStartsWith('learner-', $result->actual->learnerRef);

            $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('email', $json);
            $this->assertStringNotContainsString('password', $json);
        }
    }

    // 10. Regression compatibility with M3/M4/M5.
    public function test_m3_m4_m5_services_remain_authoritative_and_unchanged(): void
    {
        // Production services still exist in their own namespace.
        $this->assertTrue(class_exists(EvidenceValidationService::class));
        $this->assertTrue(class_exists(LearningStateInferenceService::class));
        $this->assertTrue(class_exists(AdaptiveInterventionService::class));
        $this->assertTrue(class_exists(NextLearningActionService::class));
        $this->assertTrue(class_exists(NexusClosedLoopService::class));

        // The evaluation overlay lives in a separate namespace and does not replace them.
        $this->assertTrue(class_exists(NexusEvaluationRunner::class));
        $this->assertTrue(class_exists(OutcomeComparator::class));
        $this->assertTrue(class_exists(ConstraintChecker::class));

        // Running the pipeline directly still yields the documented M4 behavior:
        // two rejections → needs_support with a remedial intervention.
        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        $result = app(NexusClosedLoopService::class)->processLearnerActivity($student->id, $activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $result['learning_state']->state);
        $this->assertNotNull($result['intervention']);
        $this->assertTrue($result['remedial_intervention_created']);
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function productionFixture(): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'difficulty' => 'medium',
            'concept' => 'loops',
            'learning_objective' => 'Write a loop.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        return [$student, $course, $activity];
    }

    private function sourceWithoutComments(string $path): string
    {
        $tokens = token_get_all(file_get_contents($path));
        $code = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }
}
