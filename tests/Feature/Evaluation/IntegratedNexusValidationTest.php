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
use App\Services\Evaluation\Integrated\IntegratedEvaluationRunner;
use App\Services\Evaluation\Integrated\IntegratedValidationReport;
use App\Services\Evaluation\Integrated\IntegrationScenario;
use App\Services\Evaluation\Integrated\IntegrationScenarioLibrary;
use App\Services\Evaluation\Intervention\InterventionEvaluationRunner;
use App\Services\Evaluation\Intervention\InterventionScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\Performance\PerformanceEvaluationRunner;
use App\Services\Evaluation\Performance\PerformanceScenarioLibrary;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-07 — Integrated NEXUS Validation.
 *
 * Validates M3–M6 as one coherent system end-to-end: cross-layer linkage
 * (evidence→state→intervention→next-action), closed-loop integrity across retries,
 * reassessment/contextual integration, provenance/task-demand consistency, privacy,
 * failure propagation, determinism, FAIL/REVIEW capability, and full regression
 * protection of every prior M6 suite.
 */
class IntegratedNexusValidationTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): IntegratedEvaluationRunner
    {
        return app(IntegratedEvaluationRunner::class);
    }

    private function scenario(string $id): IntegrationScenario
    {
        foreach ((new IntegrationScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found.");
    }

    // 1. End-to-end success path.
    public function test_success_path_end_to_end(): void
    {
        $result = $this->runner()->run($this->scenario('INT-SUCCESS-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame(LearningStateValue::Stable->value, $result->actual->terminalState);
        $this->assertFalse($result->actual->interventionPresent);
        $this->assertSame('pass', $result->dimensions['evidence_state_linkage']);
        $this->assertSame('pass', $result->dimensions['next_action_linkage']);
    }

    // 2. Repeated difficulty path with cross-layer linkage.
    public function test_repeated_difficulty_path(): void
    {
        $result = $this->runner()->run($this->scenario('INT-REPEATED-DIFFICULTY-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->terminalState);
        $this->assertTrue($result->actual->interventionPresent);
        $this->assertTrue($result->actual->interventionLinksState);
        $this->assertSame('pass', $result->dimensions['state_intervention_linkage']);
    }

    // 3. Recovery path reuses the same intervention (closed-loop integrity).
    public function test_recovery_path_reuses_same_intervention(): void
    {
        $result = $this->runner()->run($this->scenario('INT-RECOVERY-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame(LearningStateValue::Progressing->value, $result->actual->terminalState);
        $this->assertTrue($result->actual->retryConsumesSameIntervention);
        $this->assertSame('pass', $result->dimensions['retry_closed_loop_integrity']);
    }

    // 4. Insufficient-evidence path.
    public function test_insufficient_path(): void
    {
        $result = $this->runner()->run($this->scenario('INT-INSUFFICIENT-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame(LearningStateValue::InsufficientEvidence->value, $result->actual->terminalState);
        $this->assertSame('collect_more_evidence', $result->actual->terminalNextAction);
    }

    // 5. Failed retry path.
    public function test_failed_retry_path(): void
    {
        $result = $this->runner()->run($this->scenario('INT-FAILED-RETRY-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->terminalState);
        $this->assertTrue($result->actual->retryConsumesSameIntervention);
    }

    // 6. Reassessment path integrity (provenance + source-of-truth invariance).
    public function test_reassessment_path_integrity(): void
    {
        $result = $this->runner()->run($this->scenario('INT-REASSESSMENT-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertTrue($result->actual->reassessment['eligible']);
        $this->assertTrue($result->actual->reassessment['provenance_consistent']);
        $this->assertTrue($result->actual->reassessment['source_of_truth_unchanged']);
    }

    // 7. Contextual variation path → REVIEW (human-judgment boundary).
    public function test_contextual_variation_path_reports_review(): void
    {
        $result = $this->runner()->run($this->scenario('INT-CONTEXTUAL-VARIATION-PATH-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertGreaterThan(0, $result->actual->contextualVariation['contexts_count']);
        $this->assertFalse($result->actual->contextualVariation['claims_causal']);
    }

    // 8. Privacy / provenance path.
    public function test_privacy_provenance_path(): void
    {
        $result = $this->runner()->run($this->scenario('INT-PRIVACY-PROVENANCE-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame('pass', $result->dimensions['privacy']);
        $this->assertSame('pass', $result->dimensions['provenance_completeness']);
        $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('@', $json);
        $this->assertStringNotContainsString('sentinel', $json);
        $this->assertStringNotContainsString('secret learner', $json);
    }

    // 9. Error / failure path: graceful degradation with upstream intact.
    public function test_error_failure_path(): void
    {
        $result = $this->runner()->run($this->scenario('INT-ERROR-FAILURE-PATH-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status, json_encode($result->differences));
        $this->assertSame('generation_failed', $result->actual->reassessment['status']);
        $this->assertTrue($result->actual->reassessment['upstream_intact']);
        $this->assertTrue($result->actual->reassessment['source_of_truth_unchanged']);
        // Upstream loop still produced a coherent needs_support chain.
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->terminalState);
        $this->assertTrue($result->actual->interventionPresent);
    }

    // 10. Intentional divergence → FAIL detection.
    public function test_intentional_divergence_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('INT-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        // NEXUS actually produced no intervention on the success path.
        $this->assertFalse($result->actual->interventionPresent);
    }

    // 11. Task-demand consistency across all layers.
    public function test_task_demand_consistency_across_layers(): void
    {
        foreach (['INT-REPEATED-DIFFICULTY-PATH-001', 'INT-RECOVERY-PATH-001'] as $id) {
            $result = $this->runner()->run($this->scenario($id));
            $this->assertTrue($result->actual->taskDemandConsistent, "{$id} task-demand consistency");
            $this->assertFalse($result->actual->claimsCausal);
        }
    }

    // 12. Determinism / reproducibility of the integrated verdict.
    public function test_integration_is_deterministic(): void
    {
        $first = $this->runner()->run($this->scenario('INT-REPEATED-DIFFICULTY-PATH-001'));
        $second = $this->runner()->run($this->scenario('INT-REPEATED-DIFFICULTY-PATH-001'));

        $this->assertTrue($first->actual->deterministic);
        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual->terminalState, $second->actual->terminalState);
    }

    // 13. Read-only / source-of-truth protection across the whole library.
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

        $this->runner()->runMany((new IntegrationScenarioLibrary)->all());

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

    // 14. Independence + generator binding restoration.
    public function test_independence_and_binding_restoration(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/Integrated/IntegrationScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);
        $this->assertStringNotContainsString('AiAssistedReassessmentService', $code);

        $this->runner()->runMany((new IntegrationScenarioLibrary)->all());

        $generator = app(ReassessmentCandidateGenerator::class);
        $this->assertInstanceOf(
            DeterministicReassessmentCandidateGenerator::class,
            $generator,
        );
    }

    // 15. Regression protection: all prior M6 suites remain green.
    public function test_all_prior_m6_frameworks_remain_green(): void
    {
        $this->assertSame([4, 1, 1], $this->tally(app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all())));
        $this->assertSame([6, 1, 1], $this->tally(app(LearningStateValidationRunner::class)->runMany((new LearningStateScenarioLibrary)->all())));
        $this->assertSame([12, 1, 1], $this->tally(app(InterventionEvaluationRunner::class)->runMany((new InterventionScenarioLibrary)->all())));
        $this->assertSame([7, 1, 1], $this->tally(app(CognitiveAffectiveEvaluationRunner::class)->runMany((new CognitiveAffectiveScenarioLibrary)->all())));
        $this->assertSame([7, 1, 1], $this->tally(app(ExplainabilityEvaluationRunner::class)->runMany((new ExplainabilityScenarioLibrary)->all())));
        $this->assertSame([5, 1, 2], $this->tally(app(PerformanceEvaluationRunner::class)->runMany((new PerformanceScenarioLibrary)->all())));
    }

    // Aggregate report metrics.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new IntegratedValidationReport($this->runner()->runMany((new IntegrationScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(10, $summary['total']);
        $this->assertSame(8, $summary['pass']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertTrue($report->crossLayerConsistency());
        $this->assertTrue($report->privacyCompliance());
        $this->assertSame(['INT-CONTEXTUAL-VARIATION-PATH-001'], $report->reviewScenarioIds());
        $this->assertSame(['INT-DIVERGENCE-FAIL-001'], $report->blockingFailureScenarioIds());
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
