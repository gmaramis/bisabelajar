<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateTransitionType;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\LearningStateTrajectoryQuery;
use App\Services\Research\ResearchEvidenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningStateTrajectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Module $module;

    private LearningUnit $unit;

    private Activity $activity;

    private LearningStateTrajectoryQuery $trajectory;

    private ResearchEvidenceQuery $research;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create([
            'title' => 'Trajectory Course',
        ]);
        $this->module = Module::factory()->for($this->course)->published()->create();
        $this->unit = LearningUnit::factory()->for($this->module)->published()->create();
        $this->activity = Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => 'loops',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $this->trajectory = app(LearningStateTrajectoryQuery::class);
        $this->research = app(ResearchEvidenceQuery::class);
    }

    public function test_chronological_learning_state_history(): void
    {
        $late = $this->makeState(LearningStateValue::Stable, now()->subMinutes(5));
        $early = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(30));
        $mid = $this->makeState(LearningStateValue::Progressing, now()->subMinutes(15));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(
            [$early->id, $mid->id, $late->id],
            array_column($result['states'], 'learning_state_id'),
        );
        $this->assertSame(
            ['needs_support', 'progressing', 'stable'],
            $result['sequence'],
        );
        $this->assertSame('learning_states.inferred_at', $result['timestamp_semantics']['primary']);
        $this->assertSame('learning_states.id', $result['timestamp_semantics']['tie_break']);
    }

    public function test_needs_support_to_progressing_is_positive_transition(): void
    {
        $this->assertTransition(
            LearningStateValue::NeedsSupport,
            LearningStateValue::Progressing,
            LearningStateTransitionType::PositiveTransition,
        );
    }

    public function test_progressing_to_stable_is_stabilization(): void
    {
        $this->assertTransition(
            LearningStateValue::Progressing,
            LearningStateValue::Stable,
            LearningStateTransitionType::Stabilization,
        );
    }

    public function test_needs_support_to_stable_is_positive_transition(): void
    {
        $this->assertTransition(
            LearningStateValue::NeedsSupport,
            LearningStateValue::Stable,
            LearningStateTransitionType::PositiveTransition,
        );
    }

    public function test_needs_support_to_needs_support_is_persistent_support_need(): void
    {
        $this->assertTransition(
            LearningStateValue::NeedsSupport,
            LearningStateValue::NeedsSupport,
            LearningStateTransitionType::PersistentSupportNeed,
        );
    }

    public function test_stable_to_needs_support_is_deterioration_signal(): void
    {
        $this->assertTransition(
            LearningStateValue::Stable,
            LearningStateValue::NeedsSupport,
            LearningStateTransitionType::DeteriorationSignal,
        );
    }

    public function test_progressing_to_needs_support_is_deterioration_signal(): void
    {
        $this->assertTransition(
            LearningStateValue::Progressing,
            LearningStateValue::NeedsSupport,
            LearningStateTransitionType::DeteriorationSignal,
        );
    }

    public function test_stable_to_stable_is_stable_continuation(): void
    {
        $this->assertTransition(
            LearningStateValue::Stable,
            LearningStateValue::Stable,
            LearningStateTransitionType::StableContinuation,
        );
    }

    public function test_progressing_to_progressing_is_continued_progressing(): void
    {
        $this->assertTransition(
            LearningStateValue::Progressing,
            LearningStateValue::Progressing,
            LearningStateTransitionType::ContinuedProgressing,
        );
    }

    public function test_insufficient_or_ambiguous_evidence_transitions(): void
    {
        $this->assertSame(
            LearningStateTransitionType::InsufficientOrAmbiguous,
            $this->trajectory->classifyTransition(
                LearningStateValue::InsufficientEvidence,
                LearningStateValue::Progressing,
            ),
        );
        $this->assertSame(
            LearningStateTransitionType::InsufficientOrAmbiguous,
            $this->trajectory->classifyTransition(
                LearningStateValue::Stable,
                LearningStateValue::InsufficientEvidence,
            ),
        );
        // Unlisted pair (stable → progressing) is ambiguous in V1 rules.
        $this->assertSame(
            LearningStateTransitionType::InsufficientOrAmbiguous,
            $this->trajectory->classifyTransition(
                LearningStateValue::Stable,
                LearningStateValue::Progressing,
            ),
        );

        $this->makeState(LearningStateValue::InsufficientEvidence, now()->subMinutes(20));
        $this->makeState(LearningStateValue::Progressing, now()->subMinutes(10));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(
            LearningStateTransitionType::InsufficientOrAmbiguous->value,
            $result['transitions'][0]['transition_type'],
        );

        $otherActivity = Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $empty = $this->trajectory->forLearnerActivity($this->student->id, $otherActivity->id);
        $this->assertSame([], $empty['sequence']);
        $this->assertSame([], $empty['transitions']);
    }

    public function test_example_trajectory_needs_support_through_stable(): void
    {
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(40));
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(30));
        $this->makeState(LearningStateValue::Progressing, now()->subMinutes(20));
        $this->makeState(LearningStateValue::Stable, now()->subMinutes(10));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(
            ['needs_support', 'needs_support', 'progressing', 'stable'],
            $result['sequence'],
        );
        $this->assertSame(
            [
                LearningStateTransitionType::PersistentSupportNeed->value,
                LearningStateTransitionType::PositiveTransition->value,
                LearningStateTransitionType::Stabilization->value,
            ],
            array_column($result['transitions'], 'transition_type'),
        );
    }

    public function test_provenance_traces_trajectory_to_state_evidence_event(): void
    {
        $from = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(20), withEvidence: true);
        $to = $this->makeState(LearningStateValue::Progressing, now()->subMinutes(10), withEvidence: true);

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);
        $transition = $result['transitions'][0];

        $this->assertSame($from->id, $transition['from_learning_state_id']);
        $this->assertSame($to->id, $transition['to_learning_state_id']);
        $this->assertNotEmpty($transition['source_evidence_ids']);
        $this->assertNotEmpty($result['provenance']['learning_state_ids']);
        $this->assertNotEmpty($result['provenance']['validated_evidence_ids']);
        $this->assertNotEmpty($result['provenance']['learning_event_ids']);

        $evidenceId = $result['provenance']['validated_evidence_ids'][0];
        $evidence = ValidatedEvidence::query()->findOrFail($evidenceId);
        $this->assertNotNull($evidence->learning_event_id);
        $this->assertTrue($evidence->learningEvent()->exists());
    }

    public function test_historical_learning_states_are_not_overwritten_by_trajectory(): void
    {
        $first = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(20));
        $second = $this->makeState(LearningStateValue::Progressing, now()->subMinutes(10));

        $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertDatabaseHas('learning_states', [
            'id' => $first->id,
            'state' => LearningStateValue::NeedsSupport->value,
        ]);
        $this->assertDatabaseHas('learning_states', [
            'id' => $second->id,
            'state' => LearningStateValue::Progressing->value,
        ]);
        $this->assertSame(2, LearningState::query()->count());
    }

    public function test_course_context_separation_does_not_merge_unrelated_courses(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherCourse = Course::factory()->for($tutor, 'owner')->published()->public()->create([
            'title' => 'Unrelated Course',
        ]);
        $otherModule = Module::factory()->for($otherCourse)->published()->create();
        $otherUnit = LearningUnit::factory()->for($otherModule)->published()->create();
        $otherActivity = Activity::factory()->for($otherUnit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(30));
        $this->makeState(LearningStateValue::Progressing, now()->subMinutes(20));

        LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $otherActivity->id,
            'state' => LearningStateValue::Stable,
            'state_confidence' => StateConfidence::High,
            'inferred_at' => now()->subMinutes(10),
            'inference_key' => hash('sha256', 'other-course-state'),
        ]);

        $courseTrajectory = $this->trajectory->forLearnerCourse($this->student->id, $this->course->id);
        $this->assertSame(['needs_support', 'progressing'], $courseTrajectory['sequence']);
        $this->assertSame($this->course->id, $courseTrajectory['scope']['course_id']);
        $this->assertNotContains('stable', $courseTrajectory['sequence']);

        $grouped = $this->trajectory->forLearnerGroupedByCourse($this->student->id);
        $this->assertCount(2, $grouped);
        $sequences = array_map(fn (array $row): array => $row['sequence'], $grouped);
        $this->assertContains(['needs_support', 'progressing'], $sequences);
        $this->assertContains(['stable'], $sequences);
    }

    public function test_research_learner_identifier_matches_m5_01(): void
    {
        $this->makeState(LearningStateValue::Stable, now()->subMinutes(5));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(
            $this->research->researchLearnerId($this->student->id),
            $result['research_learner_id'],
        );
        $this->assertSame($this->student->id, $result['learner_id']);
    }

    public function test_deterministic_ordering_with_identical_inferred_at(): void
    {
        $at = now()->subMinutes(12);
        $a = $this->makeState(LearningStateValue::NeedsSupport, $at);
        $b = $this->makeState(LearningStateValue::NeedsSupport, $at);
        $c = $this->makeState(LearningStateValue::Progressing, $at);

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);
        $ids = array_column($result['states'], 'learning_state_id');

        $expected = collect([$a->id, $b->id, $c->id])->sort()->values()->all();
        $this->assertSame($expected, $ids);
    }

    public function test_no_mutation_of_m4_learning_state_records(): void
    {
        $state = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(10), withEvidence: true);
        $snapshot = $state->fresh()->only([
            'state',
            'state_confidence',
            'inference_key',
            'inference_rule',
            'explanation',
            'behavioral_indicators',
        ]);
        $count = LearningState::query()->count();

        $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);
        $this->trajectory->forLearnerCourse($this->student->id, $this->course->id);
        $this->trajectory->forLearnerGroupedByCourse($this->student->id);

        $this->assertSame($count, LearningState::query()->count());
        $this->assertSame($snapshot, $state->fresh()->only(array_keys($snapshot)));
        $this->assertFalse(Schema::hasTable('learning_trajectories'));
        $this->assertFalse(Schema::hasTable('state_transition_history'));
        $this->assertFalse(Schema::hasTable('research_trajectory'));
    }

    public function test_no_causal_or_effectiveness_claims(): void
    {
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(20));
        $this->makeState(LearningStateValue::Progressing, now()->subMinutes(10));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);
        $transition = $result['transitions'][0];

        $this->assertFalse($result['analysis_boundary']['claims_causal_improvement']);
        $this->assertFalse($result['analysis_boundary']['claims_intervention_effectiveness']);
        $this->assertFalse($result['analysis_boundary']['claims_treatment_effect']);
        $this->assertFalse($transition['claims_causal_improvement']);
        $this->assertFalse($transition['claims_intervention_effectiveness']);
        $this->assertStringContainsString('not a causal claim', $transition['explanation']);
        $this->assertSame(LearningStateTransitionType::PositiveTransition->value, $transition['transition_type']);
    }

    public function test_no_m5_03_weak_area_detection(): void
    {
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(10));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertFalse($result['analysis_boundary']['performs_weak_area_detection']);
        $this->assertArrayNotHasKey('weak_areas', $result);
        $this->assertFalse(class_exists('App\\Services\\Research\\WeakAreaDetectionService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\WeakAreaIdentificationService'));
    }

    private function assertTransition(
        LearningStateValue $from,
        LearningStateValue $to,
        LearningStateTransitionType $expected,
    ): void {
        $this->assertSame($expected, $this->trajectory->classifyTransition($from, $to));

        $this->makeState($from, now()->subMinutes(20));
        $this->makeState($to, now()->subMinutes(10));

        $result = $this->trajectory->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertCount(1, $result['transitions']);
        $this->assertSame($expected->value, $result['transitions'][0]['transition_type']);
        $this->assertSame(
            sprintf('%s → %s = %s', $from->value, $to->value, $expected->value),
            $result['transitions'][0]['transition_rule'],
        );
    }

    private function makeState(
        LearningStateValue $state,
        $inferredAt,
        bool $withEvidence = false,
    ): LearningState {
        $record = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid((string) $state->value, true)),
            'explanation' => 'Fixture Learning State for M5-02 trajectory tests.',
            'inference_rule' => 'fixture_m5_02',
        ]);

        if ($withEvidence) {
            $event = LearningEvent::query()->create([
                'user_id' => $this->student->id,
                'course_id' => $this->course->id,
                'activity_id' => $this->activity->id,
                'event_type' => 'submission_accepted',
                'payload' => ['seeded' => true],
                'occurred_at' => $inferredAt,
            ]);

            $evidence = ValidatedEvidence::query()->create([
                'user_id' => $this->student->id,
                'activity_id' => $this->activity->id,
                'learning_event_id' => $event->id,
                'source_record_type' => null,
                'source_record_id' => null,
                'evidence_category' => EvidenceCategory::Performance->value,
                'evidence_type' => 'submission_accepted',
                'observed_value' => ['summary' => 'fixture'],
                'context_summary' => [
                    'task_repetition' => 'new',
                    'task_difficulty' => 'medium',
                    'execution_anomaly' => 'none',
                    'network_environment' => 'unknown',
                ],
                'quality' => EvidenceQuality::Valid->value,
                'confidence' => EvidenceConfidence::High->value,
                'validation_reason' => 'Fixture evidence for M5-02.',
                'validated_at' => $inferredAt,
            ]);

            $record->validatedEvidence()->sync([$evidence->id]);
        }

        return $record->fresh(['validatedEvidence.learningEvent', 'activity.learningUnit.module.course']);
    }
}
