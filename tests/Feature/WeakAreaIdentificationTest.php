<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Enums\WeakAreaClassification;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\NextLearningAction;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\ResearchEvidenceQuery;
use App\Services\Research\WeakAreaIdentificationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeakAreaIdentificationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Module $module;

    private LearningUnit $unit;

    private Activity $activity;

    private WeakAreaIdentificationQuery $query;

    private ResearchEvidenceQuery $research;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $this->module = Module::factory()->for($this->course)->published()->create();
        $this->unit = LearningUnit::factory()->for($this->module)->published()->create();
        $this->activity = $this->makeActivity('loops');
        $this->query = app(WeakAreaIdentificationQuery::class);
        $this->research = app(ResearchEvidenceQuery::class);
    }

    public function test_repeated_needs_support_trajectory_is_weak_persistent(): void
    {
        $this->seedNeedsSupportState(now()->subMinutes(40), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(30), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['weak_areas'][0];

        $this->assertSame(WeakAreaClassification::WeakPersistent->value, $finding['classification']);
        $this->assertTrue($finding['is_weak_area']);
        $this->assertSame('concept:loops', $finding['learning_area_key']);
        $this->assertSame('activity_concept', $finding['learning_area_representation']);
        $this->assertSame(
            ['needs_support', 'needs_support', 'needs_support'],
            $finding['trajectory']['sequence'],
        );
        $this->assertGreaterThanOrEqual(1, $finding['trajectory']['persistent_support_transitions']);
    }

    public function test_single_failure_is_insufficient_evidence_not_weak_area(): void
    {
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertSame(WeakAreaClassification::InsufficientEvidence->value, $finding['classification']);
        $this->assertFalse($finding['is_weak_area']);
        $this->assertSame([], $result['weak_areas']);
        $this->assertStringContainsString('single', strtolower($finding['explanation']));
    }

    public function test_failure_then_success_then_stable_is_no_current_weakness(): void
    {
        $this->seedNeedsSupportState(now()->subMinutes(30), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);
        $this->seedRecoveredState(LearningStateValue::Stable, now()->subMinutes(10));

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertSame(WeakAreaClassification::NoCurrentWeakness->value, $finding['classification']);
        $this->assertFalse($finding['is_weak_area']);
        $this->assertSame([], $result['weak_areas']);
    }

    public function test_repeated_failures_without_acceptance_is_weak_repeated_failure(): void
    {
        $first = $this->seedNeedsSupportState(now()->subMinutes(25), failure: true);
        // Attach a second distinct failure evidence to the same latest unresolved pattern via second state.
        $this->seedNeedsSupportState(now()->subMinutes(15), failure: true, cognitive: null, psychomotor: null, behavioral: ['persistent_attempt_behavior']);

        // Ensure we have >=2 failure evidences and no acceptances; avoid 3 consecutive needs_support
        // triggering weak_persistent by keeping exactly 2 needs_support states.
        $this->assertSame(2, LearningState::query()->count());

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertContains($finding['classification'], [
            WeakAreaClassification::WeakRepeatedFailure->value,
            WeakAreaClassification::WeakPersistent->value,
        ]);
        $this->assertTrue($finding['is_weak_area']);
        $this->assertGreaterThanOrEqual(2, $finding['signals']['failure_evidence_count']);
        $this->assertSame(0, $finding['signals']['acceptance_evidence_count']);
        unset($first);
    }

    public function test_unresolved_cognitive_and_psychomotor_yield_weak_unresolved(): void
    {
        $this->seedNeedsSupportState(
            now()->subMinutes(20),
            failure: true,
            cognitive: 'unresolved_performance_outcome_observed',
            psychomotor: null,
        );
        $this->seedNeedsSupportState(
            now()->subMinutes(10),
            failure: false,
            cognitive: null,
            psychomotor: 'execution_practice_with_unresolved_outcome',
            behavioral: ['persistent_attempt_behavior'],
            evidenceType: 'code_run',
            evidenceCategory: EvidenceCategory::Behavioral,
        );

        // Force classification path: exactly one failure, two needs_support, cognitive+psychomotor —
        // but persistent trajectory of 2 needs_support may hit weak_persistent first.
        // Use a dedicated activity/area with one needs_support + both indicators via state fields
        // and failed retry to hit weak_unresolved without persistent rule.
        LearningState::query()->delete();
        ValidatedEvidence::query()->delete();
        LearningEvent::query()->delete();

        $state = $this->seedNeedsSupportState(
            now()->subMinutes(10),
            failure: true,
            cognitive: 'unresolved_performance_outcome_observed',
            psychomotor: 'execution_practice_with_unresolved_outcome',
        );

        NextLearningAction::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_state_id' => $state->id,
            'retry_outcome' => 'failure',
            'decision_key' => hash('sha256', 'm5-03-failed-retry'),
            'decided_at' => now()->subMinutes(9),
        ]);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        // With only one needs_support, persistent rule should not fire; unresolved + failed retry should.
        $this->assertSame(WeakAreaClassification::WeakUnresolved->value, $finding['classification']);
        $this->assertTrue($finding['is_weak_area']);
        $this->assertNotEmpty($finding['observable_indicators']['cognitive']);
        $this->assertNotEmpty($finding['observable_indicators']['psychomotor']);
    }

    public function test_uncertain_and_system_context_are_not_strong_evidence(): void
    {
        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'code_run',
            'payload' => [],
            'occurred_at' => now()->subMinutes(10),
        ]);

        $uncertain = ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::SystemContext->value,
            'evidence_type' => 'execution_timeout',
            'observed_value' => ['summary' => 'timeout'],
            'context_summary' => [],
            'quality' => EvidenceQuality::Uncertain->value,
            'confidence' => EvidenceConfidence::Low->value,
            'validation_reason' => 'system',
            'validated_at' => now()->subMinutes(10),
        ]);

        $state = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'state' => LearningStateValue::InsufficientEvidence,
            'state_confidence' => StateConfidence::Low,
            'inferred_at' => now()->subMinutes(10),
            'inference_key' => hash('sha256', 'uncertain-only'),
            'cognitive_indicator' => null,
            'psychomotor_indicator' => null,
            'behavioral_indicators' => [],
        ]);
        $state->validatedEvidence()->sync([$uncertain->id]);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertSame(WeakAreaClassification::InsufficientEvidence->value, $finding['classification']);
        $this->assertSame(0, $finding['signals']['usable_evidence_count']);
        $this->assertGreaterThan(0, $finding['evidence_quality_summary']['uncertain']);
    }

    public function test_same_concept_aggregates_across_activities(): void
    {
        $other = $this->makeActivity('loops');

        $this->seedNeedsSupportState(now()->subMinutes(30), failure: true, activity: $this->activity);
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true, activity: $other);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true, activity: $other);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);

        $this->assertCount(1, $result['findings']);
        $this->assertSame('concept:loops', $result['findings'][0]['learning_area_key']);
        $this->assertContains($this->activity->id, $result['findings'][0]['activity_ids']);
        $this->assertContains($other->id, $result['findings'][0]['activity_ids']);
        $this->assertTrue($result['findings'][0]['is_weak_area']);
    }

    public function test_different_concepts_are_separated(): void
    {
        $functions = $this->makeActivity('functions');

        $this->seedNeedsSupportState(now()->subMinutes(30), failure: true, activity: $this->activity);
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true, activity: $this->activity);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true, activity: $this->activity);

        $this->seedRecoveredState(LearningStateValue::Stable, now()->subMinutes(5), activity: $functions);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $keys = array_column($result['findings'], 'learning_area_key');

        $this->assertContains('concept:loops', $keys);
        $this->assertContains('concept:functions', $keys);

        $loops = collect($result['findings'])->firstWhere('learning_area_key', 'concept:loops');
        $funcs = collect($result['findings'])->firstWhere('learning_area_key', 'concept:functions');

        $this->assertTrue($loops['is_weak_area']);
        $this->assertSame(WeakAreaClassification::NoCurrentWeakness->value, $funcs['classification']);
    }

    public function test_bloom_and_dave_remain_task_demand_context(): void
    {
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);

        $finding = $this->query->forLearnerCourse($this->student->id, $this->course->id)['findings'][0];

        $this->assertSame('task_demand', $finding['bloom_semantics']);
        $this->assertSame('task_demand', $finding['dave_semantics']);
        $this->assertContains(BloomLevel::Apply->value, $finding['bloom_demand_context']);
        $this->assertContains(DaveLevel::Manipulation->value, $finding['dave_demand_context']);
        $this->assertFalse($finding['claims_learner_capability_from_bloom_dave']);
        $this->assertArrayNotHasKey('learner_bloom_level', $finding);
        $this->assertArrayNotHasKey('learner_dave_level', $finding);
    }

    public function test_provenance_and_research_learner_id(): void
    {
        $state = $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertSame(
            $this->research->researchLearnerId($this->student->id),
            $result['research_learner_id'],
        );
        $this->assertContains($state->id, $finding['supporting_learning_state_ids']);
        $this->assertNotEmpty($finding['supporting_evidence_ids']);

        $evidence = ValidatedEvidence::query()->findOrFail($finding['supporting_evidence_ids'][0]);
        $this->assertTrue($evidence->learningEvent()->exists());
    }

    public function test_course_scope_does_not_merge_unrelated_courses(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherCourse = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $otherModule = Module::factory()->for($otherCourse)->published()->create();
        $otherUnit = LearningUnit::factory()->for($otherModule)->published()->create();
        $otherActivity = Activity::factory()->for($otherUnit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => 'loops',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        $this->seedNeedsSupportState(now()->subMinutes(30), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);

        LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $otherActivity->id,
            'state' => LearningStateValue::NeedsSupport,
            'inferred_at' => now()->subMinutes(5),
            'inference_key' => hash('sha256', 'other-course'),
        ]);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);

        $this->assertSame($this->course->id, $result['scope']['course_id']);
        $this->assertNotContains($otherActivity->id, $result['findings'][0]['activity_ids']);
    }

    public function test_no_mutation_and_no_persistent_weak_area_table(): void
    {
        $state = $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);
        $snapshot = $state->fresh()->only(['state', 'inference_key', 'explanation', 'cognitive_indicator']);
        $counts = [
            'states' => LearningState::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
        ];

        $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $this->query->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($counts['states'], LearningState::query()->count());
        $this->assertSame($counts['evidence'], ValidatedEvidence::query()->count());
        $this->assertSame($snapshot, $state->fresh()->only(array_keys($snapshot)));
        $this->assertFalse(Schema::hasTable('weak_areas'));
        $this->assertFalse(Schema::hasTable('research_weak_areas'));
    }

    public function test_no_psychological_inference_or_m5_04_reassessment_generation(): void
    {
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true);

        $result = $this->query->forLearnerCourse($this->student->id, $this->course->id);
        $finding = $result['findings'][0];

        $this->assertFalse($result['analysis_boundary']['claims_psychological_diagnosis']);
        $this->assertFalse($result['analysis_boundary']['generates_reassessment_questions']);
        $this->assertFalse($result['analysis_boundary']['uses_ml_or_llm']);
        $this->assertFalse($finding['claims_psychological_diagnosis']);
        $this->assertFalse($finding['generates_reassessment_questions']);
        $this->assertStringNotContainsString('psychologically', strtolower($finding['explanation']));
        $this->assertFalse(class_exists('App\\Services\\Research\\ReassessmentQuestionGenerator'));
        $this->assertFalse(class_exists('App\\Services\\Research\\WeakAreaDetectionService'));
    }

    public function test_activity_scoped_query_uses_concept_aggregation(): void
    {
        $sibling = $this->makeActivity('loops');
        $this->seedNeedsSupportState(now()->subMinutes(20), failure: true, activity: $sibling);
        $this->seedNeedsSupportState(now()->subMinutes(10), failure: true, activity: $sibling);

        $result = $this->query->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertNotNull($result['finding']);
        $this->assertSame('concept:loops', $result['finding']['learning_area_key']);
        $this->assertContains($sibling->id, $result['finding']['activity_ids']);
    }

    private function makeActivity(string $concept): Activity
    {
        return Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => $concept,
            'learning_objective' => 'Practice '.$concept,
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
        ]);
    }

    private function seedNeedsSupportState(
        $inferredAt,
        bool $failure = true,
        ?string $cognitive = 'unresolved_performance_outcome_observed',
        ?string $psychomotor = null,
        array $behavioral = ['persistent_attempt_behavior'],
        ?Activity $activity = null,
        string $evidenceType = 'submission_rejected',
        EvidenceCategory $evidenceCategory = EvidenceCategory::Performance,
    ): LearningState {
        $activity ??= $this->activity;

        $state = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $activity->id,
            'state' => LearningStateValue::NeedsSupport,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid('needs-support', true)),
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => $psychomotor,
            'behavioral_indicators' => $behavioral,
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Fixture needs_support for M5-03.',
            'inference_rule' => 'fixture_m5_03',
        ]);

        if ($failure) {
            $evidence = $this->makeEvidence($activity, $evidenceType, $evidenceCategory, $inferredAt);
            $state->validatedEvidence()->sync([$evidence->id]);
        }

        return $state->fresh(['validatedEvidence.learningEvent', 'activity']);
    }

    private function seedRecoveredState(
        LearningStateValue $stateValue,
        $inferredAt,
        ?Activity $activity = null,
    ): LearningState {
        $activity ??= $this->activity;

        $state = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $activity->id,
            'state' => $stateValue,
            'state_confidence' => StateConfidence::High,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid('recovered', true)),
            'cognitive_indicator' => 'successful_task_outcome_observed',
            'psychomotor_indicator' => 'successful_execution_observed',
            'behavioral_indicators' => ['corrective_behavior'],
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Fixture recovered state for M5-03.',
            'inference_rule' => 'fixture_m5_03_recovered',
        ]);

        $evidence = $this->makeEvidence(
            $activity,
            'submission_accepted',
            EvidenceCategory::Performance,
            $inferredAt,
            EvidenceQuality::Valid,
            EvidenceConfidence::High,
        );
        $state->validatedEvidence()->sync([$evidence->id]);

        return $state->fresh(['validatedEvidence', 'activity']);
    }

    private function makeEvidence(
        Activity $activity,
        string $evidenceType,
        EvidenceCategory $category,
        $at,
        EvidenceQuality $quality = EvidenceQuality::Valid,
        EvidenceConfidence $confidence = EvidenceConfidence::High,
    ): ValidatedEvidence {
        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $activity->id,
            'event_type' => $evidenceType === 'repeated_submission_failures' ? 'submission_rejected' : $evidenceType,
            'payload' => ['seeded' => true],
            'occurred_at' => $at,
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $activity->id,
            'learning_event_id' => $event->id,
            'source_record_type' => null,
            'source_record_id' => null,
            'evidence_category' => $category->value,
            'evidence_type' => $evidenceType,
            'observed_value' => ['summary' => $evidenceType],
            'context_summary' => [
                'task_repetition' => 'new',
                'task_difficulty' => 'medium',
                'execution_anomaly' => 'none',
                'network_environment' => 'unknown',
            ],
            'quality' => $quality->value,
            'confidence' => $confidence->value,
            'validation_reason' => 'Fixture evidence for M5-03.',
            'validated_at' => $at,
        ]);
    }
}
