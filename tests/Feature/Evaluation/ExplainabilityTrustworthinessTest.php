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
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveEvaluationRunner;
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveScenarioLibrary;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\Explainability\ExplainabilityEvaluationRunner;
use App\Services\Evaluation\Explainability\ExplainabilityScenario;
use App\Services\Evaluation\Explainability\ExplainabilityScenarioLibrary;
use App\Services\Evaluation\Explainability\ExplainabilityValidationReport;
use App\Services\Evaluation\Intervention\InterventionEvaluationRunner;
use App\Services\Evaluation\Intervention\InterventionScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\ContextualVariationQuery;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-05 — Explainability & Trustworthiness.
 *
 * Validates that NEXUS decisions/research outputs across seven components are
 * transparent (reason + rule), provenance-complete, deterministic, uncertainty-
 * aware, task-demand-bounded, privacy-safe, and free of psychological/causal
 * claims. Includes FAIL detection, a human-judgment REVIEW boundary, read-only
 * protection, independence, and M6-01..04 + M3-M5 compatibility.
 */
class ExplainabilityTrustworthinessTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): ExplainabilityEvaluationRunner
    {
        return app(ExplainabilityEvaluationRunner::class);
    }

    private function scenario(string $id): ExplainabilityScenario
    {
        foreach ((new ExplainabilityScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found.");
    }

    // 1. Every component produces a transparent, grounded, bounded explanation.
    public function test_all_seven_components_are_explainable(): void
    {
        $components = [
            'EXP-LEARNING-STATE-001', 'EXP-INTERVENTION-001', 'EXP-NEXT-ACTION-001',
            'EXP-WEAK-AREA-001', 'EXP-REASSESSMENT-001', 'EXP-RESPONSE-001', 'EXP-CONTEXTUAL-VARIATION-001',
        ];

        foreach ($components as $id) {
            $result = $this->runner()->run($this->scenario($id));
            $this->assertSame(EvaluationStatus::Pass, $result->status, "Component scenario {$id} should pass: ".json_encode($result->differences));
            $this->assertSame('pass', $result->dimensions['transparency']);
            $this->assertNotSame('', trim($result->actual->explanationText));
        }
    }

    // 2. Transparency: reason and rule present where required.
    public function test_transparency_reason_and_rule_present(): void
    {
        $ls = $this->runner()->run($this->scenario('EXP-LEARNING-STATE-001'));
        $this->assertNotSame('', trim($ls->actual->explanationText));
        $this->assertNotNull($ls->actual->rule);

        $response = $this->runner()->run($this->scenario('EXP-RESPONSE-001'));
        $this->assertNotNull($response->actual->rule);
    }

    // 3. Provenance completeness / evidence grounding.
    public function test_provenance_completeness(): void
    {
        foreach (['EXP-LEARNING-STATE-001', 'EXP-INTERVENTION-001', 'EXP-WEAK-AREA-001', 'EXP-RESPONSE-001'] as $id) {
            $result = $this->runner()->run($this->scenario($id));
            $this->assertTrue($result->actual->hasProvenance, "{$id} should expose provenance");
            $this->assertTrue($result->provenanceCheck['traceable']);
            $this->assertSame('pass', $result->dimensions['provenance_completeness']);
        }
    }

    // 4. Uncertainty visibility (confidence surfaced).
    public function test_uncertainty_visibility(): void
    {
        $ls = $this->runner()->run($this->scenario('EXP-LEARNING-STATE-001'));
        $this->assertTrue($ls->actual->confidenceVisible);
        $this->assertNotNull($ls->actual->confidenceValue);
        $this->assertSame('pass', $ls->dimensions['uncertainty_visibility']);
    }

    // 5. Bloom/Dave wording remains task demand.
    public function test_task_demand_wording(): void
    {
        foreach (['EXP-LEARNING-STATE-001', 'EXP-WEAK-AREA-001', 'EXP-REASSESSMENT-001', 'EXP-RESPONSE-001'] as $id) {
            $result = $this->runner()->run($this->scenario($id));
            $this->assertTrue($result->actual->bloomDaveTaskDemand, "{$id} should keep Bloom/Dave as task demand");
        }
    }

    // 6. No psychological diagnosis and no unsupported causal claim.
    public function test_no_diagnosis_and_no_causal_claim(): void
    {
        foreach ((new ExplainabilityScenarioLibrary)->all() as $scenario) {
            $result = $this->runner()->run($scenario);
            $this->assertTrue($result->constraintCheck['checks']['no_diagnosis'], "{$scenario->scenarioId} diagnosis-free");
            $this->assertFalse($result->actual->claimsCausal, "{$scenario->scenarioId} makes no causal claim");
        }
    }

    // 7. Determinism: same evidence/config → same explanation & rule.
    public function test_deterministic_explanations(): void
    {
        $first = $this->runner()->run($this->scenario('EXP-LEARNING-STATE-001'));
        $second = $this->runner()->run($this->scenario('EXP-LEARNING-STATE-001'));

        $this->assertTrue($first->actual->deterministic);
        $this->assertSame($first->actual->explanationText, $second->actual->explanationText);
        $this->assertSame($first->actual->rule, $second->actual->rule);
        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->dimensions, $second->dimensions);
    }

    // 8. Intentional divergence → FAIL detection.
    public function test_intentional_divergence_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('EXP-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        $this->assertSame('fail', $result->dimensions['explanation_content']);
    }

    // 9. Human-judgment boundary → REVIEW.
    public function test_understandability_boundary_reports_review(): void
    {
        $result = $this->runner()->run($this->scenario('EXP-UNDERSTANDABILITY-REVIEW-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertTrue($result->expected->ambiguous);
    }

    // 10. Privacy-safe output across the whole library.
    public function test_privacy_safe_output(): void
    {
        foreach ($this->runner()->runMany((new ExplainabilityScenarioLibrary)->all()) as $result) {
            $this->assertSame('pass', $result->dimensions['privacy']);
            $this->assertStringStartsWith('learner-', $result->actual->learnerRef);
            $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('sentinel', $json);
            $this->assertStringNotContainsString('secret learner', $json);
        }
    }

    // 11. Read-only / source-of-truth protection.
    public function test_evaluation_does_not_mutate_production_source_of_truth(): void
    {
        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => true]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);
        $stateValue = $state->state->value;

        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        $this->runner()->runMany((new ExplainabilityScenarioLibrary)->all());

        $after = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];

        $this->assertSame($before, $after);
        $this->assertSame($stateValue, LearningState::query()->find($state->id)->state->value);
    }

    // 12. Expected-outcome independence.
    public function test_expected_outcomes_are_independent(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/Explainability/ExplainabilityScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('LearningStateInferenceService', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);
        $this->assertStringNotContainsString('InterventionResponseQuery', $code);

        $this->assertSame(0, LearningState::query()->count());
        $fail = $this->runner()->run($this->scenario('EXP-DIVERGENCE-FAIL-001'));
        $this->assertSame(EvaluationStatus::Fail, $fail->status);
    }

    // 13-15. M6-01 / M6-02 / M6-03 / M6-04 compatibility.
    public function test_prior_m6_frameworks_remain_green(): void
    {
        $this->assertSame([4, 1, 1], $this->tally(app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all())));
        $this->assertSame([6, 1, 1], $this->tally(app(LearningStateValidationRunner::class)->runMany((new LearningStateScenarioLibrary)->all())));
        $this->assertSame([12, 1, 1], $this->tally(app(InterventionEvaluationRunner::class)->runMany((new InterventionScenarioLibrary)->all())));
        $this->assertSame([7, 1, 1], $this->tally(app(CognitiveAffectiveEvaluationRunner::class)->runMany((new CognitiveAffectiveScenarioLibrary)->all())));
    }

    // 16. M3/M4/M5 regression compatibility.
    public function test_m4_m5_services_remain_authoritative(): void
    {
        $this->assertTrue(class_exists(LearningStateInferenceService::class));
        $this->assertTrue(class_exists(ContextualVariationQuery::class));

        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => true]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);
        $this->assertSame(LearningStateValue::Stable, $state->state);
    }

    // Aggregate report metrics.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new ExplainabilityValidationReport($this->runner()->runMany((new ExplainabilityScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(9, $summary['total']);
        $this->assertSame(7, $summary['pass']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertTrue($report->constraintCompliance());
        $this->assertTrue($report->provenanceCompliance());
        $this->assertSame(['EXP-UNDERSTANDABILITY-REVIEW-001'], $report->reviewScenarioIds());
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
