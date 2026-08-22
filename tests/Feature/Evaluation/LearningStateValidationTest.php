<?php

namespace Tests\Feature\Evaluation;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\LearningStateValue;
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
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\LearningState\ExpectedLearningState;
use App\Services\Evaluation\LearningState\LearningStateScenario;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationReport;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-02 — Learning State Validation.
 *
 * Validates M4-T03 Learning State inference against independently authored
 * scenarios: state classification, confidence, insufficient/boundary handling,
 * evidence-quality boundaries, indicators, determinism, provenance, privacy,
 * read-only protection, independence, and M6-01/M3/M4/M5 regression compatibility.
 */
class LearningStateValidationTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): LearningStateValidationRunner
    {
        return app(LearningStateValidationRunner::class);
    }

    private function scenario(string $id): LearningStateScenario
    {
        foreach ((new LearningStateScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found in library.");
    }

    // 1. Expected normal state (stable) passes.
    public function test_stable_state_scenario_passes(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-STABLE-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::Stable->value, $result->actual->state);
        $this->assertSame('stable_successful_outcome', $result->actual->inferenceRule);
        $this->assertSame('pass', $result->dimensions['state_classification']);
        $this->assertSame([], $result->differences);
    }

    // 2. needs_support.
    public function test_needs_support_state_scenario_passes(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-NEEDS-SUPPORT-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->state);
        $this->assertContains('persistent_attempt_behavior', $result->actual->behavioralIndicators);
        $this->assertSame('pass', $result->dimensions['behavioral_indicator']);
    }

    // 3. progressing.
    public function test_progressing_state_scenario_passes(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-PROGRESSING-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::Progressing->value, $result->actual->state);
        $this->assertSame('corrective_application_observed', $result->actual->cognitiveIndicator);
        $this->assertSame('pass', $result->dimensions['cognitive_indicator']);
        $this->assertSame('pass', $result->dimensions['psychomotor_indicator']);
    }

    // 4. stable is validated above; here validate insufficient evidence.
    public function test_insufficient_evidence_scenario_passes(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-INSUFFICIENT-EMPTY-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::InsufficientEvidence->value, $result->actual->state);
        $this->assertSame(0, $result->actual->usableCount());
        $this->assertSame('high', $result->actual->stateConfidence);
    }

    // 5. Evidence-quality boundary (low-confidence-only) reads as insufficient.
    public function test_evidence_quality_boundary_scenario_passes(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-QUALITY-BOUNDARY-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(LearningStateValue::InsufficientEvidence->value, $result->actual->state);
        $this->assertSame(1, $result->actual->usableCount());
    }

    // 6. Low-confidence / ambiguous boundary yields REVIEW.
    public function test_ambiguous_boundary_scenario_reports_review(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-LOWCONF-AMBIGUOUS-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertTrue($result->expected->ambiguous);
        $this->assertContains($result->actual->state, [
            LearningStateValue::NeedsSupport->value,
            LearningStateValue::InsufficientEvidence->value,
        ]);
    }

    // 7. FAIL detection: divergent independent expectation is surfaced as FAIL.
    public function test_divergent_expectation_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        $this->assertSame(LearningStateValue::Stable->value, $result->actual->state);
        $this->assertSame('fail', $result->dimensions['state_classification']);
    }

    // 8. Deterministic repeated evaluation.
    public function test_validation_is_deterministic_and_inference_is_idempotent(): void
    {
        $first = $this->runner()->run($this->scenario('LSV-PROGRESSING-001'));
        $second = $this->runner()->run($this->scenario('LSV-PROGRESSING-001'));

        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->differences, $second->differences);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual->state, $second->actual->state);
        $this->assertSame($first->actual->learnerRef, $second->actual->learnerRef);

        // T03 idempotency: same evidence set does not create a duplicate state.
        $this->assertTrue($first->actual->idempotent);
        $this->assertSame('pass', $first->dimensions['idempotency']);

        // Report ordering is stable regardless of input order.
        $scenarios = (new LearningStateScenarioLibrary)->all();
        $forward = new LearningStateValidationReport($this->runner()->runMany($scenarios));
        $reversed = new LearningStateValidationReport($this->runner()->runMany(array_reverse($scenarios)));
        $this->assertSame(
            array_column($forward->toArray()['scenarios'], 'scenario_id'),
            array_column($reversed->toArray()['scenarios'], 'scenario_id'),
        );
    }

    // 9. Provenance points to real evidence/events and back to the scenario.
    public function test_provenance_is_preserved(): void
    {
        $result = $this->runner()->run($this->scenario('LSV-PROGRESSING-001'));

        $this->assertTrue($result->provenanceCheck['traceable']);
        $this->assertTrue($result->provenanceCheck['links_back_to_scenario']);
        $this->assertSame('LSV-PROGRESSING-001', $result->actual->provenance['scenario_id']);
        $this->assertNotEmpty($result->actual->provenance['validated_evidence_ids']);
        $this->assertNotEmpty($result->actual->provenance['learning_event_ids']);
        $this->assertSame('pass', $result->dimensions['provenance']);
    }

    // 10. Read-only / source-of-truth protection.
    public function test_validation_does_not_mutate_production_source_of_truth(): void
    {
        // Establish a real production Learning State via the authoritative service.
        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, [
            'status' => 'success', 'passes_evaluation' => true,
        ]);
        $productionState = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);
        $stateId = $productionState->id;
        $stateValue = $productionState->state->value;

        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        $results = $this->runner()->runMany((new LearningStateScenarioLibrary)->all());
        $this->assertNotEmpty($results);

        $after = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        $this->assertSame($before, $after, 'Validation must not add or remove production learning records.');

        $reloaded = LearningState::query()->find($stateId);
        $this->assertNotNull($reloaded);
        $this->assertSame($stateValue, $reloaded->state->value);
        $this->assertSame(1, LearningState::query()->count());
    }

    // 11. Expected-outcome independence.
    public function test_expected_outcomes_are_independent_of_the_implementation_under_test(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/LearningState/LearningStateScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('LearningStateInferenceService', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);
        $this->assertStringNotContainsString('inferForLearnerActivity', $code);

        // Expected values are authored literals, available with no pipeline/DB access.
        $expected = $this->scenario('LSV-STABLE-001')->expected;
        $this->assertInstanceOf(ExpectedLearningState::class, $expected);
        $this->assertSame(LearningStateValue::Stable, $expected->state);
        $this->assertNotSame('', $expected->rationale);
        $this->assertSame(0, LearningState::query()->count());

        // The FAIL scenario proves Expected is not derived from Actual.
        $failResult = $this->runner()->run($this->scenario('LSV-DIVERGENCE-FAIL-001'));
        $this->assertNotSame($failResult->expected->state->value, $failResult->actual->state);
    }

    // 12a. Regression compatibility with M3/M4/M5 (T03 unchanged behavior).
    public function test_m4_t03_behavior_remains_authoritative_and_unchanged(): void
    {
        $this->assertTrue(class_exists(LearningStateInferenceService::class));

        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);
        // T03 alone creates no intervention.
        $this->assertSame(0, AdaptiveIntervention::query()->count());
    }

    // 12b. Regression compatibility with M6-01 (framework preserved).
    public function test_m6_01_framework_remains_green_and_separate(): void
    {
        $this->assertTrue(class_exists(NexusEvaluationRunner::class));

        $m601 = app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all());
        $pass = $fail = $review = 0;
        foreach ($m601 as $r) {
            match ($r->status) {
                EvaluationStatus::Pass => $pass++,
                EvaluationStatus::Fail => $fail++,
                EvaluationStatus::Review => $review++,
            };
        }

        $this->assertSame(6, count($m601));
        $this->assertSame(4, $pass);
        $this->assertSame(1, $fail);
        $this->assertSame(1, $review);
    }

    // Aggregate report metrics are coherent across the full library.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new LearningStateValidationReport($this->runner()->runMany((new LearningStateScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(8, $summary['total']);
        $this->assertSame($summary['total'], $summary['pass'] + $summary['fail'] + $summary['review']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertSame(6, $summary['pass']);
        $this->assertTrue($report->provenanceCompliance());
        $this->assertSame(0, $report->constraintViolationCount());
        $this->assertSame(['LSV-LOWCONF-AMBIGUOUS-001'], $report->unresolvedReviewScenarioIds());
    }

    // Privacy-safe output across the whole library.
    public function test_evaluation_output_is_privacy_safe(): void
    {
        foreach ($this->runner()->runMany((new LearningStateScenarioLibrary)->all()) as $result) {
            $this->assertTrue($result->constraintCheck['checks']['privacy_safe']);
            $this->assertSame('pass', $result->dimensions['privacy']);
            $this->assertStringStartsWith('learner-', $result->actual->learnerRef);

            $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('email', $json);
            $this->assertStringNotContainsString('password', $json);
        }
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
