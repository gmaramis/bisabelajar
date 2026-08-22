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
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveScenario;
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveScenarioLibrary;
use App\Services\Evaluation\CognitiveAffective\CognitiveAffectiveValidationReport;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\Intervention\InterventionEvaluationRunner;
use App\Services\Evaluation\Intervention\InterventionScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-04 — Cognitive-Affective Model Validation.
 *
 * Validates the observable cognitive/psychomotor/behavioral indicators of the
 * existing M4-T03 model: observability, cognitive/behavioral interpretation,
 * cognitive-affective separation, no clinical inference, Bloom/Dave task-demand
 * separation, evidence sufficiency/ambiguity, temporal/provenance, privacy,
 * determinism, independence, FAIL/REVIEW capability, and M6-01/02/03 + M3-M5
 * compatibility.
 */
class CognitiveAffectiveModelValidationTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): CognitiveAffectiveEvaluationRunner
    {
        return app(CognitiveAffectiveEvaluationRunner::class);
    }

    private function scenario(string $id): CognitiveAffectiveScenario
    {
        foreach ((new CognitiveAffectiveScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found.");
    }

    // 1. Cognitive indicator validation.
    public function test_cognitive_indicator_validation(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-COGNITIVE-CORRECTIVE-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame('corrective_application_observed', $result->actual->cognitiveIndicator);
        $this->assertSame('pass', $result->dimensions['cognitive_interpretation']);
    }

    // 2 & 6. Observable behavioral indicator validation (persistent engagement + attempt).
    public function test_observable_behavioral_indicator_validation(): void
    {
        $engagement = $this->runner()->run($this->scenario('CAV-BEHAVIORAL-PERSISTENT-ENGAGEMENT-001'));
        $this->assertSame(EvaluationStatus::Pass, $engagement->status);
        $this->assertContains('persistent_engagement', $engagement->actual->behavioralIndicators);

        $attempt = $this->runner()->run($this->scenario('CAV-BEHAVIORAL-PERSISTENT-ATTEMPT-001'));
        $this->assertSame(EvaluationStatus::Pass, $attempt->status);
        $this->assertContains('persistent_attempt_behavior', $attempt->actual->behavioralIndicators);
    }

    // 3 & 4. Repeated unsuccessful attempt / repeated rejection pattern.
    public function test_repeated_rejection_is_unresolved_outcome(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-COGNITIVE-UNRESOLVED-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame('unresolved_performance_outcome_observed', $result->actual->cognitiveIndicator);
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result->actual->state);
        $this->assertSame([], $result->actual->behavioralIndicators);
    }

    // 5 & 6. Psychomotor practice + timeout/uncertain behavior.
    public function test_psychomotor_unresolved_and_uncertain_behavior(): void
    {
        $psychomotor = $this->runner()->run($this->scenario('CAV-PSYCHOMOTOR-UNRESOLVED-001'));
        $this->assertSame(EvaluationStatus::Pass, $psychomotor->status);
        $this->assertSame('execution_practice_with_unresolved_outcome', $psychomotor->actual->psychomotorIndicator);

        $uncertain = $this->runner()->run($this->scenario('CAV-INSUFFICIENT-UNCERTAIN-001'));
        $this->assertSame(EvaluationStatus::Pass, $uncertain->status);
        $this->assertNull($uncertain->actual->cognitiveIndicator);
        $this->assertNull($uncertain->actual->psychomotorIndicator);
        $this->assertSame([], $uncertain->actual->behavioralIndicators);
    }

    // 7. Insufficient evidence remains insufficient (reduced engagement).
    public function test_reduced_engagement_and_insufficient_evidence(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-BEHAVIORAL-REDUCED-ENGAGEMENT-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertContains('reduced_activity_engagement', $result->actual->behavioralIndicators);
        $this->assertSame(LearningStateValue::InsufficientEvidence->value, $result->actual->state);
    }

    // 8. Ambiguous behavioral pattern → REVIEW.
    public function test_conflicting_signals_report_review(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-CONFLICTING-REVIEW-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertTrue($result->expected->ambiguous);
    }

    // 9. Intentional divergence → FAIL detection.
    public function test_intentional_divergence_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
        $this->assertSame('successful_task_outcome_observed', $result->actual->cognitiveIndicator);
    }

    // 10 & 11. Cognitive-affective separation + no psychological/clinical inference.
    public function test_no_psychological_or_clinical_inference_across_library(): void
    {
        foreach ($this->runner()->runMany((new CognitiveAffectiveScenarioLibrary)->all()) as $result) {
            $this->assertTrue($result->constraintCheck['checks']['no_clinical_inference']);
            $this->assertTrue($result->constraintCheck['checks']['indicators_observable']);
            $this->assertSame('pass', $result->dimensions['no_clinical_inference']);

            $haystack = strtolower(json_encode([
                $result->actual->cognitiveIndicator,
                $result->actual->psychomotorIndicator,
                $result->actual->behavioralIndicators,
            ], JSON_THROW_ON_ERROR));
            foreach (['anxiety', 'anxious', 'depression', 'depressed', 'clinical', 'emotional disorder', 'mental health'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $haystack);
            }
        }
    }

    // 12. Bloom/Dave task-demand separation (indicator never equals demand level).
    public function test_bloom_dave_task_demand_separation(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-COGNITIVE-CORRECTIVE-001'));

        $this->assertTrue($result->constraintCheck['checks']['bloom_dave_task_demand_only']);
        $this->assertSame('pass', $result->dimensions['task_demand_separation']);
        $this->assertNotSame($result->actual->bloomDemand, $result->actual->cognitiveIndicator);
        $this->assertNotSame($result->actual->daveDemand, $result->actual->psychomotorIndicator);
    }

    // 13 & 14. Contextual interpretation + temporal ordering: a single isolated
    // event does not fabricate a definitive state; ordered events give a defined state.
    public function test_contextual_interpretation_avoids_isolated_event_certainty(): void
    {
        // One isolated rejection is not treated as definitive needs_support.
        $single = $this->runner()->run($this->scenario('CAV-PSYCHOMOTOR-UNRESOLVED-001'));
        $this->assertNotSame(LearningStateValue::Stable->value, $single->actual->state);

        // Ordered rejection→acceptance is interpreted as corrective progressing.
        $ordered = $this->runner()->run($this->scenario('CAV-COGNITIVE-CORRECTIVE-001'));
        $this->assertSame(LearningStateValue::Progressing->value, $ordered->actual->state);
    }

    // 15. Provenance: indicator → evidence → event → state.
    public function test_provenance_is_preserved(): void
    {
        $result = $this->runner()->run($this->scenario('CAV-BEHAVIORAL-PERSISTENT-ATTEMPT-001'));

        $this->assertTrue($result->provenanceCheck['traceable']);
        $this->assertTrue($result->provenanceCheck['links_back_to_scenario']);
        $this->assertSame('CAV-BEHAVIORAL-PERSISTENT-ATTEMPT-001', $result->actual->provenance['scenario_id']);
        $this->assertNotEmpty($result->actual->provenance['validated_evidence_ids']);
        $this->assertNotEmpty($result->actual->provenance['learning_event_ids']);
        $this->assertSame('pass', $result->dimensions['evidence_traceability']);
    }

    // 16. Privacy-safe output.
    public function test_privacy_safe_output(): void
    {
        foreach ($this->runner()->runMany((new CognitiveAffectiveScenarioLibrary)->all()) as $result) {
            $this->assertSame('pass', $result->dimensions['privacy']);
            $this->assertStringStartsWith('learner-', $result->actual->learnerRef);
            $json = strtolower(json_encode($result->actual->toArray(), JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('email', $json);
        }
    }

    // 17. Read-only / source-of-truth protection.
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

        $this->runner()->runMany((new CognitiveAffectiveScenarioLibrary)->all());

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

    // 18. Deterministic repeated evaluation.
    public function test_evaluation_is_deterministic(): void
    {
        $first = $this->runner()->run($this->scenario('CAV-COGNITIVE-CORRECTIVE-001'));
        $second = $this->runner()->run($this->scenario('CAV-COGNITIVE-CORRECTIVE-001'));

        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->differences, $second->differences);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual->cognitiveIndicator, $second->actual->cognitiveIndicator);
        $this->assertTrue($first->actual->deterministic);
        $this->assertSame('pass', $first->dimensions['determinism']);
    }

    // 19. Expected-outcome independence.
    public function test_expected_outcomes_are_independent(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/CognitiveAffective/CognitiveAffectiveScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('LearningStateInferenceService', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);
        $this->assertStringNotContainsString('inferForLearnerActivity', $code);

        $this->assertSame(0, LearningState::query()->count());
        $fail = $this->runner()->run($this->scenario('CAV-DIVERGENCE-FAIL-001'));
        $this->assertNotSame($fail->expected->cognitiveIndicator, $fail->actual->cognitiveIndicator);
    }

    // 20-22. M6-01 / M6-02 / M6-03 compatibility.
    public function test_prior_m6_frameworks_remain_green(): void
    {
        [$p1, $f1, $r1] = $this->tally(app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all()));
        $this->assertSame([4, 1, 1], [$p1, $f1, $r1]);

        [$p2, $f2, $r2] = $this->tally(app(LearningStateValidationRunner::class)->runMany((new LearningStateScenarioLibrary)->all()));
        $this->assertSame([6, 1, 1], [$p2, $f2, $r2]);

        [$p3, $f3, $r3] = $this->tally(app(InterventionEvaluationRunner::class)->runMany((new InterventionScenarioLibrary)->all()));
        $this->assertSame([12, 1, 1], [$p3, $f3, $r3]);
    }

    // 23. M3/M4/M5 regression compatibility.
    public function test_m4_t03_model_remains_authoritative(): void
    {
        $this->assertTrue(class_exists(LearningStateInferenceService::class));

        [$student, $course, $activity] = $this->productionFixture();
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        LearningEvent::record('submission_rejected', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => false]);
        $state = app(LearningStateInferenceService::class)->inferForLearnerActivity($student->id, $activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);
        $this->assertSame(0, AdaptiveIntervention::query()->count());
    }

    // Aggregate report metrics.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new CognitiveAffectiveValidationReport($this->runner()->runMany((new CognitiveAffectiveScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(9, $summary['total']);
        $this->assertSame(7, $summary['pass']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertTrue($report->indicatorObservabilityCompliance());
        $this->assertSame(0, $report->clinicalInferenceViolationCount());
        $this->assertTrue($report->provenanceCompliance());
        $this->assertSame(['CAV-CONFLICTING-REVIEW-001'], $report->reviewScenarioIds());
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
