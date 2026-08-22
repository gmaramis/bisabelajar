<?php

namespace Tests\Feature\Evaluation;

use App\Contracts\Research\ReassessmentCandidateGenerator;
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
use App\Models\ReassessmentCandidate;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveEvaluationRunner;
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveScenarioLibrary;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\Explainability\ExplainabilityEvaluationRunner;
use App\Services\Evaluation\Explainability\ExplainabilityScenarioLibrary;
use App\Services\Evaluation\Intervention\InterventionEvaluationRunner;
use App\Services\Evaluation\Intervention\InterventionScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\Performance\PerformanceEvaluationReport;
use App\Services\Evaluation\Performance\PerformanceEvaluationRunner;
use App\Services\Evaluation\Performance\PerformanceScenario;
use App\Services\Evaluation\Performance\PerformanceScenarioLibrary;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use App\Services\Research\ResearchEvidenceExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-06 — System & AI Performance Evaluation.
 *
 * Evaluates objective reliability behaviors (determinism, timeout/failure handling,
 * AI-provider abstraction, AI-not-decision-maker) as PASS/FAIL, reports raw
 * latency/query/memory measurements as REVIEW (no invented thresholds), and covers
 * FAIL detection, read-only protection, privacy, independence, and M6-01..05 + M3-M5
 * compatibility.
 */
class SystemAiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): PerformanceEvaluationRunner
    {
        return app(PerformanceEvaluationRunner::class);
    }

    private function scenario(string $id): PerformanceScenario
    {
        foreach ((new PerformanceScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found.");
    }

    // 1. Deterministic component consistency (inference + closed loop).
    public function test_deterministic_component_consistency(): void
    {
        $inference = $this->runner()->run($this->scenario('PERF-DETERMINISM-INFERENCE-001'));
        $this->assertSame(EvaluationStatus::Pass, $inference->status);
        $this->assertTrue($inference->actual->deterministic);
        $this->assertSame('pass', $inference->dimensions['determinism']);

        $loop = $this->runner()->run($this->scenario('PERF-DETERMINISM-CLOSED-LOOP-001'));
        $this->assertSame(EvaluationStatus::Pass, $loop->status);
        $this->assertTrue($loop->actual->deterministic);
    }

    // 2. Timeout handling.
    public function test_timeout_handling(): void
    {
        $result = $this->runner()->run($this->scenario('PERF-TIMEOUT-HANDLING-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame('generation_failed', $result->actual->failureStatus);
        $this->assertTrue($result->actual->failureHandled);
        $this->assertTrue($result->actual->sourceOfTruthUnchanged);
    }

    // 3. Retry / failure handling (AI unavailable).
    public function test_failure_handling(): void
    {
        $result = $this->runner()->run($this->scenario('PERF-FAILURE-HANDLING-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame('generation_failed', $result->actual->failureStatus);
        $this->assertTrue($result->actual->sourceOfTruthUnchanged);
    }

    // 4. AI provider abstraction + AI is not the decision-maker.
    public function test_ai_provider_abstraction(): void
    {
        $result = $this->runner()->run($this->scenario('PERF-AI-ABSTRACTION-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame('m6_06_custom_generator', $result->actual->aiGeneratorIdentity);
        $this->assertFalse($result->actual->aiIsDecisionMaker);
    }

    // 5. Measurement scenarios are reported as REVIEW (no invented thresholds).
    public function test_measurement_scenarios_report_review_with_measurements(): void
    {
        foreach (['PERF-MEASUREMENT-INFERENCE-001', 'PERF-MEASUREMENT-EXPORT-001'] as $id) {
            $result = $this->runner()->run($this->scenario($id));
            $this->assertSame(EvaluationStatus::Review, $result->status);
            $this->assertGreaterThanOrEqual(0.0, $result->actual->elapsedMs);
            $this->assertGreaterThan(0, $result->actual->queryCount);
            $this->assertSame('review', $result->dimensions['measurement']);
        }
    }

    // 6. Intentional divergence → FAIL detection.
    public function test_intentional_divergence_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('PERF-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        // The pipeline actually degraded to generation_failed; the authored expectation was wrong.
        $this->assertSame('generation_failed', $result->actual->failureStatus);
    }

    // 7. Reproducibility of the evaluation verdicts (not raw timings).
    public function test_evaluation_verdicts_are_reproducible(): void
    {
        $first = $this->runner()->run($this->scenario('PERF-DETERMINISM-INFERENCE-001'));
        $second = $this->runner()->run($this->scenario('PERF-DETERMINISM-INFERENCE-001'));

        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual->deterministic, $second->actual->deterministic);
    }

    // 8. Read-only / source-of-truth protection across the whole library.
    public function test_evaluation_does_not_mutate_production_source_of_truth(): void
    {
        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => true]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);

        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
            'candidates' => ReassessmentCandidate::query()->count(),
        ];

        $this->runner()->runMany((new PerformanceScenarioLibrary)->all());

        $after = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
            'candidates' => ReassessmentCandidate::query()->count(),
        ];

        $this->assertSame($before, $after);
        $this->assertSame($state->state->value, LearningState::query()->find($state->id)->state->value);
    }

    // 9. Privacy-safe output.
    public function test_privacy_safe_output(): void
    {
        foreach ($this->runner()->runMany((new PerformanceScenarioLibrary)->all()) as $result) {
            $this->assertSame('pass', $result->dimensions['privacy']);
            $this->assertStringStartsWith('learner-', $result->actual->learnerRef);
            $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('email', $json);
        }
    }

    // 10. Independence: the scenario library does not import production components.
    public function test_expected_criteria_are_independent(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/Performance/PerformanceScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('LearningStateInferenceService', $code);
        $this->assertStringNotContainsString('AiAssistedReassessmentService', $code);

        $fail = $this->runner()->run($this->scenario('PERF-DIVERGENCE-FAIL-001'));
        $this->assertSame(EvaluationStatus::Fail, $fail->status);
    }

    // 11. The AI generator binding is restored after evaluation (no leakage).
    public function test_generator_binding_restored_after_evaluation(): void
    {
        $this->runner()->runMany((new PerformanceScenarioLibrary)->all());

        $generator = app(ReassessmentCandidateGenerator::class);
        $this->assertInstanceOf(
            DeterministicReassessmentCandidateGenerator::class,
            $generator,
        );
    }

    // 12-15. M6-01..05 compatibility.
    public function test_prior_m6_frameworks_remain_green(): void
    {
        $this->assertSame([4, 1, 1], $this->tally(app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all())));
        $this->assertSame([6, 1, 1], $this->tally(app(LearningStateValidationRunner::class)->runMany((new LearningStateScenarioLibrary)->all())));
        $this->assertSame([12, 1, 1], $this->tally(app(InterventionEvaluationRunner::class)->runMany((new InterventionScenarioLibrary)->all())));
        $this->assertSame([7, 1, 1], $this->tally(app(CognitiveAffectiveEvaluationRunner::class)->runMany((new CognitiveAffectiveScenarioLibrary)->all())));
        $this->assertSame([7, 1, 1], $this->tally(app(ExplainabilityEvaluationRunner::class)->runMany((new ExplainabilityScenarioLibrary)->all())));
    }

    // 16. M3/M4/M5 regression compatibility.
    public function test_m4_m5_services_remain_authoritative(): void
    {
        $this->assertTrue(class_exists(ResearchEvidenceExportService::class));

        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => true]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);
        $this->assertSame(LearningStateValue::Stable, $state->state);
    }

    // Aggregate report metrics.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new PerformanceEvaluationReport($this->runner()->runMany((new PerformanceScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(8, $summary['total']);
        $this->assertSame(5, $summary['pass']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(2, $summary['review']);
        $this->assertTrue($report->privacyCompliance());
        $this->assertNotEmpty($report->measurements());
    }

    /**
     * @param  list<object>  $results
     * @return array{0: int, 1: int, 2: int}
     */
    private function tally(array $results): array
    {
        $pass = $fail = $review = 0;
        foreach ($results as $r) {
            match ($r->status) {
                EvaluationStatus::Pass => $pass++,
                EvaluationStatus::Fail => $fail++,
                EvaluationStatus::Review => $review++,
            };
        }

        return [$pass, $fail, $review];
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
            'concept' => 'loops',
            'learning_objective' => 'Write a loop.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
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
