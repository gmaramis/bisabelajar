<?php

namespace Tests\Feature\Evaluation;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
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
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Evaluation\Intervention\InterventionEvaluationReport;
use App\Services\Evaluation\Intervention\InterventionEvaluationRunner;
use App\Services\Evaluation\Intervention\InterventionEvaluationScenario;
use App\Services\Evaluation\Intervention\InterventionScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateScenarioLibrary;
use App\Services\Evaluation\LearningState\LearningStateValidationRunner;
use App\Services\Evaluation\NexusEvaluationRunner;
use App\Services\Evaluation\ScenarioLibrary;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\InterventionResponseQuery;
use App\Services\Research\NextLearningActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M6-03 — Intervention & Reassessment Evaluation.
 *
 * Validates the M6-03 overlay over T04 (intervention selection), T05 (next action),
 * M5-04 (reassessment), and M5-05 (observed response): selection correctness,
 * insufficient-evidence handling, reassessment eligibility/validation, response
 * classification, temporal semantics, provenance, privacy, determinism,
 * independence, FAIL/REVIEW capability, and M6-01/M6-02/M3-M5 compatibility.
 */
class InterventionReassessmentEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private function runner(): InterventionEvaluationRunner
    {
        return app(InterventionEvaluationRunner::class);
    }

    private function scenario(string $id): InterventionEvaluationScenario
    {
        foreach ((new InterventionScenarioLibrary)->all() as $scenario) {
            if ($scenario->scenarioId() === $id) {
                return $scenario;
            }
        }

        $this->fail("Scenario {$id} not found in library.");
    }

    // 1. Intervention selection validation.
    public function test_intervention_selection_validation(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-INTV-PERSISTENT-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertSame(InterventionType::Hint->value, $result->actual['intervention_type']);
        $this->assertTrue($result->actual['is_remedial']);
        $this->assertNotNull($result->actual['socratic_type']);
        $this->assertFalse($result->actual['provides_direct_answer']);
        $this->assertSame('pass', $result->dimensions['idempotency']);

        $cognitive = $this->runner()->run($this->scenario('IEV-INTV-COGNITIVE-001'));
        $this->assertSame(EvaluationStatus::Pass, $cognitive->status);
        $this->assertSame(InterventionType::SocraticQuestion->value, $cognitive->actual['intervention_type']);
    }

    // 2. Intervention absence / insufficient-evidence case.
    public function test_intervention_absence_on_insufficient_evidence(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-INTV-INSUFFICIENT-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertFalse($result->actual['is_remedial']);
        $this->assertFalse($result->actual['is_strong']);
        $this->assertSame('insufficient_evidence_no_strong_intervention', $result->actual['selection_rule']);
    }

    // 3. Next learning action validation (+ boundary).
    public function test_next_learning_action_validation(): void
    {
        $guided = $this->runner()->run($this->scenario('IEV-ACTION-GUIDED-RETRY-001'));
        $this->assertSame(EvaluationStatus::Pass, $guided->status);
        $this->assertSame('guided_retry', $guided->actual['next_action']);

        $collect = $this->runner()->run($this->scenario('IEV-ACTION-COLLECT-001'));
        $this->assertSame(EvaluationStatus::Pass, $collect->status);
        $this->assertSame('collect_more_evidence', $collect->actual['next_action']);
    }

    // 4 & 5. Reassessment candidate generation + specification/validation.
    public function test_reassessment_candidate_generation_and_validation(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-REASSESS-VALIDATED-001'));

        $this->assertSame(EvaluationStatus::Pass, $result->status);
        $this->assertTrue($result->actual['eligible']);
        $this->assertSame('validated', $result->actual['status']);
        $this->assertSame('loops', $result->actual['spec_concept']);
        $this->assertSame('task_demand', $result->actual['spec_bloom_semantics']);
        $this->assertTrue($result->actual['candidate_present']);
        $this->assertSame('pass', $result->dimensions['spec_alignment']);
    }

    public function test_reassessment_insufficient_and_recovered_not_eligible(): void
    {
        $insufficient = $this->runner()->run($this->scenario('IEV-REASSESS-INSUFFICIENT-001'));
        $this->assertSame(EvaluationStatus::Pass, $insufficient->status);
        $this->assertFalse($insufficient->actual['eligible']);
        $this->assertSame('not_eligible_insufficient_evidence', $insufficient->actual['status']);

        $recovered = $this->runner()->run($this->scenario('IEV-REASSESS-RECOVERED-001'));
        $this->assertSame(EvaluationStatus::Pass, $recovered->status);
        $this->assertFalse($recovered->actual['eligible']);
        $this->assertSame('not_eligible_recovered', $recovered->actual['status']);
    }

    // 6 & 7. Intervention response classification + observed improvement signal.
    public function test_intervention_response_classification(): void
    {
        $positive = $this->runner()->run($this->scenario('IEV-RESP-POSITIVE-001'));
        $this->assertSame(EvaluationStatus::Pass, $positive->status);
        $this->assertSame('positive_response', $positive->actual['response_classification']);
        $this->assertSame('observed_improvement', $positive->actual['observed_improvement_signal']);
        $this->assertTrue($positive->actual['observed_improvement']);
        // Observational, never causal.
        $this->assertFalse($positive->actual['claims_intervention_caused_improvement']);

        $persistent = $this->runner()->run($this->scenario('IEV-RESP-PERSISTENT-001'));
        $this->assertSame(EvaluationStatus::Pass, $persistent->status);
        $this->assertSame('negative_or_persistent_difficulty', $persistent->actual['response_classification']);
        $this->assertFalse($persistent->actual['observed_improvement']);
    }

    // 8. Temporal ordering: intervention.created_at is the cut; no invented delivery timestamp.
    public function test_temporal_ordering_has_no_invented_delivery_timestamp(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-RESP-POSITIVE-001'));

        $this->assertFalse($result->actual['delivery_timestamp_available']);
        $this->assertFalse($result->provenanceCheck['delivery_timestamp_invented']);
        $this->assertSame('pass', $result->dimensions['temporal']);
        $this->assertNotNull($result->actual['intervention_available_at']);
        $this->assertNotNull($result->actual['after_state_inferred_at']);
    }

    // 9. Provenance across kinds.
    public function test_provenance_is_preserved(): void
    {
        $response = $this->runner()->run($this->scenario('IEV-RESP-POSITIVE-001'));
        $this->assertTrue($response->provenanceCheck['traceable']);
        $this->assertNotNull($response->actual['provenance']['adaptive_intervention_id']);

        $reassess = $this->runner()->run($this->scenario('IEV-REASSESS-VALIDATED-001'));
        $this->assertTrue($reassess->provenanceCheck['traceable']);
        $this->assertNotEmpty($reassess->actual['provenance']['validated_evidence_ids']);
    }

    // 10. Privacy-safe output across the whole library (sentinel PII must not leak).
    public function test_evaluation_output_is_privacy_safe(): void
    {
        foreach ($this->runner()->runMany((new InterventionScenarioLibrary)->all()) as $result) {
            $this->assertSame('pass', $result->dimensions['privacy']);
            $json = strtolower(json_encode($result->actual, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('@', $json);
            $this->assertStringNotContainsString('sentinel', $json);
            $this->assertStringNotContainsString('secret learner', $json);
            $this->assertStringNotContainsString('email', $json);
        }
    }

    // 11. Read-only / source-of-truth protection.
    public function test_evaluation_does_not_mutate_production_source_of_truth(): void
    {
        [$student, , $activity] = $this->productionFixture();
        $state = LearningState::factory()->create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'state' => LearningStateValue::NeedsSupport,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => now()->subMinutes(15),
            'inference_key' => hash('sha256', 'm6-03-readonly'),
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        AdaptiveIntervention::factory()->create([
            'user_id' => $student->id,
            'activity_id' => $activity->id,
            'learning_state_id' => $state->id,
            'intervention_type' => InterventionType::GuidedRetry,
            'is_remedial' => true,
            'is_strong' => true,
            'selection_rule' => 'readonly_fixture',
            'reason' => 'x',
            'content' => 'x',
            'intervention_key' => hash('sha256', 'm6-03-readonly-int'),
            'metadata' => ['validated_evidence_ids' => []],
        ]);

        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
            'candidates' => ReassessmentCandidate::query()->count(),
        ];

        $results = $this->runner()->runMany((new InterventionScenarioLibrary)->all());
        $this->assertNotEmpty($results);

        $after = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
            'candidates' => ReassessmentCandidate::query()->count(),
        ];

        $this->assertSame($before, $after, 'Evaluation must not add or remove production records.');
    }

    // 12. Deterministic repeated evaluation.
    public function test_evaluation_is_deterministic(): void
    {
        $first = $this->runner()->run($this->scenario('IEV-INTV-PERSISTENT-001'));
        $second = $this->runner()->run($this->scenario('IEV-INTV-PERSISTENT-001'));

        $this->assertSame($first->status, $second->status);
        $this->assertSame($first->differences, $second->differences);
        $this->assertSame($first->dimensions, $second->dimensions);
        $this->assertSame($first->actual['intervention_type'], $second->actual['intervention_type']);

        $scenarios = (new InterventionScenarioLibrary)->all();
        $forward = new InterventionEvaluationReport($this->runner()->runMany($scenarios));
        $reversed = new InterventionEvaluationReport($this->runner()->runMany(array_reverse($scenarios)));
        $this->assertSame(
            array_column($forward->toArray()['scenarios'], 'scenario_id'),
            array_column($reversed->toArray()['scenarios'], 'scenario_id'),
        );
    }

    // 13. Expected-outcome independence.
    public function test_expected_outcomes_are_independent_of_the_implementation_under_test(): void
    {
        $code = $this->sourceWithoutComments(app_path('Services/Evaluation/Intervention/InterventionScenarioLibrary.php'));
        $this->assertStringNotContainsString('App\\Services\\Research', $code);
        $this->assertStringNotContainsString('AdaptiveInterventionService', $code);
        $this->assertStringNotContainsString('AiAssistedReassessmentService', $code);
        $this->assertStringNotContainsString('InterventionResponseQuery', $code);
        $this->assertStringNotContainsString('NextLearningActionService', $code);
        $this->assertStringNotContainsString('NexusClosedLoopService', $code);

        // Behavioral: divergent expectation disagrees with actual → proves independence.
        $fail = $this->runner()->run($this->scenario('IEV-INTV-DIVERGENCE-FAIL-001'));
        $this->assertSame(EvaluationStatus::Fail, $fail->status);
        $this->assertSame(InterventionType::Reinforcement->value, $fail->actual['intervention_type']);
    }

    // 14. Intentional divergence → FAIL detection.
    public function test_intentional_divergence_reports_fail(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-INTV-DIVERGENCE-FAIL-001'));

        $this->assertSame(EvaluationStatus::Fail, $result->status);
        $this->assertNotEmpty($result->differences);
    }

    // 15. Ambiguous/boundary case → REVIEW.
    public function test_ambiguous_reassessment_reports_review(): void
    {
        $result = $this->runner()->run($this->scenario('IEV-REASSESS-UNRESOLVED-REVIEW-001'));

        $this->assertSame(EvaluationStatus::Review, $result->status);
        $this->assertTrue($result->actual['eligible']);
    }

    // 16. M6-01 compatibility.
    public function test_m6_01_framework_remains_green(): void
    {
        $results = app(NexusEvaluationRunner::class)->runMany((new ScenarioLibrary)->all());
        [$pass, $fail, $review] = $this->tally($results);

        $this->assertSame(6, count($results));
        $this->assertSame(4, $pass);
        $this->assertSame(1, $fail);
        $this->assertSame(1, $review);
    }

    // 17. M6-02 compatibility.
    public function test_m6_02_framework_remains_green(): void
    {
        $results = app(LearningStateValidationRunner::class)->runMany((new LearningStateScenarioLibrary)->all());
        [$pass, $fail, $review] = $this->tally($results);

        $this->assertSame(8, count($results));
        $this->assertSame(6, $pass);
        $this->assertSame(1, $fail);
        $this->assertSame(1, $review);
    }

    // 18. M3/M4/M5 regression compatibility.
    public function test_m4_m5_services_remain_authoritative(): void
    {
        $this->assertTrue(class_exists(AdaptiveInterventionService::class));
        $this->assertTrue(class_exists(NextLearningActionService::class));
        $this->assertTrue(class_exists(AiAssistedReassessmentService::class));
        $this->assertTrue(class_exists(InterventionResponseQuery::class));

        // M5-05 still separates context/outcome/interpretation and makes no causal claim.
        $result = $this->runner()->run($this->scenario('IEV-RESP-POSITIVE-001'));
        $this->assertFalse($result->actual['claims_causal_effectiveness']);
        $this->assertFalse($result->actual['claims_treatment_effect']);
    }

    // Aggregate report metrics.
    public function test_report_metrics_are_coherent(): void
    {
        $report = new InterventionEvaluationReport($this->runner()->runMany((new InterventionScenarioLibrary)->all()));
        $summary = $report->summary();

        $this->assertSame(14, $summary['total']);
        $this->assertSame($summary['total'], $summary['pass'] + $summary['fail'] + $summary['review']);
        $this->assertSame(1, $summary['fail']);
        $this->assertSame(1, $summary['review']);
        $this->assertSame(12, $summary['pass']);
        $this->assertTrue($report->constraintCompliance());
        $this->assertTrue($report->provenanceCompliance());
        $this->assertSame(['IEV-REASSESS-UNRESOLVED-REVIEW-001'], $report->reviewScenarioIds());
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
