<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\InterventionType;
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
use App\Services\Research\NexusClosedLoopService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NexusClosedLoopIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private NexusClosedLoopService $loop;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($this->course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $this->activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'difficulty' => 'medium',
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $this->loop = app(NexusClosedLoopService::class);
    }

    public function test_scenario_a_successful_learning_loop_continues_without_remedial_intervention(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);

        $result = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertContains($result['learning_state']->state, [
            LearningStateValue::Stable,
            LearningStateValue::Progressing,
        ]);
        $this->assertNull($result['intervention']);
        $this->assertFalse($result['remedial_intervention_created']);
        $this->assertSame(NextLearningActionType::Continue, $result['next_action']->action);
        $this->assertSame(0, AdaptiveIntervention::query()->count());
        $this->assertNotEmpty($result['cycle_id']);
        $this->assertNotEmpty($result['validated_evidence_ids']);
    }

    public function test_scenario_b_needs_support_loop_with_intervention_and_retry_evidence(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $first = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $first['learning_state']->state);
        $this->assertNotNull($first['intervention']);
        $this->assertTrue($first['remedial_intervention_created']);
        $this->assertTrue($first['intervention']->is_remedial);

        $this->record('code_run', ['status' => 'success']);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $second = $this->loop->processAfterRetry(
            $this->student->id,
            $this->activity->id,
            $first['intervention'],
            'failure',
        );

        $this->assertSame(LearningStateValue::NeedsSupport, $second['learning_state']->state);
        $this->assertContains($second['next_action']->action, [
            NextLearningActionType::PracticeAgain,
            NextLearningActionType::ReviewConcept,
            NextLearningActionType::GuidedRetry,
            NextLearningActionType::Reassessment,
        ]);
        $this->assertSame($first['intervention']->id, $second['next_action']->adaptive_intervention_id);
    }

    public function test_scenario_c_intervention_then_successful_retry_continues(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $before = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::NeedsSupport, $before['learning_state']->state);
        $intervention = $before['intervention'];
        $this->assertNotNull($intervention);

        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);

        $after = $this->loop->processAfterRetry(
            $this->student->id,
            $this->activity->id,
            $intervention,
            'success',
        );

        $this->assertSame(LearningStateValue::Progressing, $after['learning_state']->state);
        $this->assertSame(NextLearningActionType::Continue, $after['next_action']->action);
        $this->assertFalse($after['remedial_intervention_created']);
    }

    public function test_scenario_d_intervention_then_failed_retry_stays_supported(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $before = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $intervention = $before['intervention'];
        $this->assertNotNull($intervention);

        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $after = $this->loop->processAfterRetry(
            $this->student->id,
            $this->activity->id,
            $intervention,
            'failure',
        );

        $this->assertSame(LearningStateValue::NeedsSupport, $after['learning_state']->state);
        $this->assertContains($after['next_action']->action, [
            NextLearningActionType::PracticeAgain,
            NextLearningActionType::ReviewConcept,
            NextLearningActionType::GuidedRetry,
            NextLearningActionType::Reassessment,
        ]);
        $this->assertNotSame(NextLearningActionType::Continue, $after['next_action']->action);
    }

    public function test_scenario_e_insufficient_evidence_collects_more_then_loops_back(): void
    {
        $first = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::InsufficientEvidence, $first['learning_state']->state);
        $this->assertNull($first['intervention']);
        $this->assertSame(NextLearningActionType::CollectMoreEvidence, $first['next_action']->action);

        $second = $this->loop->recordEvidenceAndProcess(
            'submission_accepted',
            $this->student->id,
            $this->course->id,
            $this->activity->id,
            ['status' => 'success', 'passes_evaluation' => true],
        );

        $this->assertNotNull($second['learning_event']);
        $this->assertGreaterThan(0, $second['learning_event']->validatedEvidence()->count());
        $this->assertSame(LearningStateValue::Stable, $second['learning_state']->state);
        $this->assertSame(NextLearningActionType::Continue, $second['next_action']->action);
    }

    public function test_scenario_f_reassessment_decision_then_new_evidence_reenters_loop(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $supported = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $intervention = $supported['intervention'];
        $this->assertNotNull($intervention);

        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $decisionPass = $this->loop->processAfterRetry(
            $this->student->id,
            $this->activity->id,
            $intervention,
            'failure',
        );

        $this->assertContains($decisionPass['next_action']->action, [
            NextLearningActionType::Reassessment,
            NextLearningActionType::PracticeAgain,
            NextLearningActionType::ReviewConcept,
            NextLearningActionType::GuidedRetry,
        ]);
        $this->assertFalse($decisionPass['provenance']['creates_reassessment_question']);
        $this->assertFalse(class_exists('App\\Services\\Research\\ReassessmentQuestionGenerator'));

        // Reassessment activity response = new evidence re-entering T02 without T06 generating questions.
        $reentry = $this->loop->recordEvidenceAndProcess(
            'submission_accepted',
            $this->student->id,
            $this->course->id,
            $this->activity->id,
            ['status' => 'success', 'passes_evaluation' => true],
            $intervention,
            'success',
        );

        $this->assertSame(LearningStateValue::Progressing, $reentry['learning_state']->state);
        $this->assertSame(NextLearningActionType::Continue, $reentry['next_action']->action);
    }

    public function test_provenance_traces_full_closed_loop_chain(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $result = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $provenance = $result['provenance'];
        $this->assertNotEmpty($provenance['learning_event_ids']);
        $this->assertNotEmpty($provenance['validated_evidence_ids']);
        $this->assertSame($result['learning_state']->id, $provenance['learning_state_id']);
        $this->assertSame($result['intervention']->id, $provenance['adaptive_intervention_id']);
        $this->assertSame($result['next_action']->id, $provenance['next_learning_action_id']);

        $evidence = ValidatedEvidence::query()->find($provenance['validated_evidence_ids'][0]);
        $this->assertNotNull($evidence);
        $this->assertTrue($evidence->learningEvent()->exists());
        $this->assertTrue($result['next_action']->learningState()->exists());
        $this->assertTrue($result['intervention']->learningState()->exists());
    }

    public function test_idempotent_orchestration_does_not_duplicate_intervention_or_next_action(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);

        $first = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $second = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($first['learning_state']->id, $second['learning_state']->id);
        $this->assertSame($first['next_action']->id, $second['next_action']->id);
        $this->assertSame($first['cycle_id'], $second['cycle_id']);
        $this->assertSame(1, LearningState::query()->count());
        $this->assertSame(1, NextLearningAction::query()->count());
        $this->assertSame(0, AdaptiveIntervention::query()->count());
    }

    public function test_needs_support_idempotent_intervention_on_repeat_orchestration(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $first = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $second = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($first['intervention']->id, $second['intervention']->id);
        $this->assertSame(1, AdaptiveIntervention::query()->count());
        $this->assertSame(1, NextLearningAction::query()->where('learning_state_id', $first['learning_state']->id)->count());
    }

    public function test_no_false_loop_for_stable_or_progressing(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $stable = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        $this->assertNull($stable['intervention']);
        $this->assertSame(NextLearningActionType::Continue, $stable['next_action']->action);

        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        // Force a progressing pattern on a fresh activity-like sequence by accepting after reject
        // on same activity: rejection + earlier acceptance already present → progressing
        $progressing = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);
        // With rejection after acceptance, fusion may become needs_support or progressing depending on order.
        // Ensure progressing path never invents remedial intervention when state is progressing/stable.
        if (in_array($progressing['learning_state']->state, [LearningStateValue::Progressing, LearningStateValue::Stable], true)) {
            $this->assertNull($progressing['intervention']);
            $this->assertFalse($progressing['remedial_intervention_created']);
            $this->assertSame(NextLearningActionType::Continue, $progressing['next_action']->action);
        }
    }

    public function test_uncertain_system_evidence_alone_does_not_force_strong_support_loop(): void
    {
        $this->record('code_run', ['status' => 'timeout']);

        $result = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::InsufficientEvidence, $result['learning_state']->state);
        $this->assertNull($result['intervention']);
        $this->assertSame(NextLearningActionType::CollectMoreEvidence, $result['next_action']->action);
    }

    public function test_scope_protection_no_m5_ml_llm_or_question_generator(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $result = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        $this->assertFalse($result['provenance']['longitudinal_analysis']);
        $this->assertFalse($result['provenance']['ml_or_llm_orchestration']);
        $this->assertFalse($result['provenance']['creates_reassessment_question']);
        $this->assertFalse(Schema::hasTable('nexus_cycles'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LongitudinalAnalyticsService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningRecommendationService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\ReassessmentQuestionGenerator'));
        $this->assertFalse(class_exists('App\\Services\\Research\\InterventionEffectivenessService'));
    }

    public function test_t02_t03_t04_t05_remain_authoritative_components(): void
    {
        $this->assertTrue(class_exists(\App\Services\Research\EvidenceValidationService::class));
        $this->assertTrue(class_exists(\App\Services\Research\LearningStateInferenceService::class));
        $this->assertTrue(class_exists(\App\Services\Research\AdaptiveInterventionService::class));
        $this->assertTrue(class_exists(\App\Services\Research\NextLearningActionService::class));
        $this->assertTrue(class_exists(\App\Services\Research\NexusClosedLoopService::class));

        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $result = $this->loop->processLearnerActivity($this->student->id, $this->activity->id);

        // Orchestration must not invent intervention types outside T04 enum.
        $this->assertContains($result['intervention']->intervention_type, InterventionType::cases());
        // Orchestration must not invent next-action types outside T05 enum.
        $this->assertContains($result['next_action']->action, NextLearningActionType::cases());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $eventType, array $payload = []): LearningEvent
    {
        return LearningEvent::record(
            $eventType,
            $this->student->id,
            $this->course->id,
            $this->activity->id,
            $payload,
        );
    }
}
