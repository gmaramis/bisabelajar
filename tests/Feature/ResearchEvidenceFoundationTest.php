<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LanguageExecutionProfile;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\NextLearningAction;
use App\Models\ProgrammingActivity;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NextLearningActionService;
use App\Services\Research\ResearchEvidenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResearchEvidenceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Module $module;

    private LearningUnit $unit;

    private Activity $activity;

    private ResearchEvidenceQuery $query;

    private LearningStateInferenceService $stateService;

    private AdaptiveInterventionService $interventionService;

    private NextLearningActionService $nextActionService;

    private LanguageExecutionProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create([
            'title' => 'Research Course Alpha',
        ]);
        $this->module = Module::factory()->for($this->course)->published()->create([
            'title' => 'Module One',
        ]);
        $this->unit = LearningUnit::factory()->for($this->module)->published()->create([
            'title' => 'Unit Loops',
        ]);
        $this->activity = Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'title' => 'Loop Drill',
            'difficulty' => 'medium',
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        $this->profile = LanguageExecutionProfile::query()->create([
            'identifier' => 'python-m5-01',
            'display_name' => 'Python M5-01',
            'file_extension' => '.py',
            'source_filename' => 'solution.py',
            'docker_image' => 'python:3.12-alpine',
            'compile_command' => null,
            'run_command' => 'python /workspace/solution.py',
            'execution_mode' => 'interpreted',
            'timeout_seconds' => 10,
            'memory_limit_mb' => 64,
            'cpu_limit' => 1,
            'network_enabled' => false,
            'enabled' => true,
            'environment_variables' => [],
            'allowed_files' => [],
        ]);

        ProgrammingActivity::createForActivity($this->activity, $this->profile, [
            'starter_code' => 'print("hello")',
        ]);

        $this->query = app(ResearchEvidenceQuery::class);
        $this->stateService = app(LearningStateInferenceService::class);
        $this->interventionService = app(AdaptiveInterventionService::class);
        $this->nextActionService = app(NextLearningActionService::class);
    }

    public function test_evidence_provenance_traces_to_learning_event(): void
    {
        $event = $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ], now()->subMinutes(5));

        $evidence = $event->validatedEvidence()->first();
        $this->assertNotNull($evidence);

        $provenance = $this->query->evidenceProvenance($evidence);

        $this->assertSame($evidence->id, $provenance['validated_evidence_id']);
        $this->assertSame($event->id, $provenance['learning_event_id']);
        $this->assertSame($event->id, $provenance['learning_event']['id']);
        $this->assertSame('submission_accepted', $provenance['learning_event']['event_type']);
        $this->assertSame('learning_event.occurred_at', $provenance['pedagogical_at_semantics']);
        $this->assertNotNull($provenance['quality']);
        $this->assertNotNull($provenance['confidence']);
    }

    public function test_learning_state_provenance_traces_to_evidence(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);

        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $provenance = $this->query->learningStateProvenance($state);

        $this->assertSame($state->id, $provenance['learning_state_id']);
        $this->assertNotEmpty($provenance['validated_evidence_ids']);
        $this->assertNotEmpty($provenance['learning_event_ids']);
        $this->assertSame(
            $state->validatedEvidence->pluck('id')->sort()->values()->all(),
            $provenance['validated_evidence_ids'],
        );

        foreach ($provenance['validated_evidence'] as $row) {
            $this->assertNotNull($row['learning_event_id']);
            $this->assertArrayHasKey('quality', $row);
            $this->assertArrayHasKey('confidence', $row);
        }
    }

    public function test_intervention_provenance_traces_to_state_and_evidence(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);

        $intervention = $this->interventionService->createForLearningState($state);
        $provenance = $this->query->interventionProvenance($intervention);

        $this->assertSame($intervention->id, $provenance['adaptive_intervention_id']);
        $this->assertSame($state->id, $provenance['learning_state_id']);
        $this->assertNotNull($provenance['learning_state']);
        $this->assertNotEmpty($provenance['learning_state']['validated_evidence_ids']);
        $this->assertNotEmpty($provenance['selection_rule']);
        $this->assertNotEmpty($provenance['reason']);
    }

    public function test_next_action_provenance_traces_to_state_and_intervention(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $intervention = $this->interventionService->createForLearningState($state);
        $action = $this->nextActionService->decideForLearningState($state, $intervention);

        $provenance = $this->query->nextActionProvenance($action);

        $this->assertSame($action->id, $provenance['next_learning_action_id']);
        $this->assertSame($state->id, $provenance['learning_state_id']);
        $this->assertSame($intervention->id, $provenance['adaptive_intervention_id']);
        $this->assertNotNull($provenance['learning_state']);
        $this->assertNotNull($provenance['adaptive_intervention']);
        $this->assertNotEmpty($provenance['decision_rule']);
    }

    public function test_historical_learning_states_are_preserved(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $needsSupport = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::NeedsSupport, $needsSupport->state);

        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $progressing = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::Progressing, $progressing->state);

        // Separate activity reaching stable preserves cross-activity history.
        $stableActivity = Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'concept' => 'lists',
        ]);
        LearningEvent::record(
            'submission_accepted',
            $this->student->id,
            $this->course->id,
            $stableActivity->id,
            ['status' => 'success', 'passes_evaluation' => true],
        );
        $stable = $this->stateService->inferForLearnerActivity($this->student->id, $stableActivity->id);
        $this->assertSame(LearningStateValue::Stable, $stable->state);

        $history = $this->query->learningStateHistory($this->student->id);

        $this->assertTrue($history->contains(fn (LearningState $state): bool => $state->id === $needsSupport->id));
        $this->assertTrue($history->contains(fn (LearningState $state): bool => $state->id === $progressing->id));
        $this->assertTrue($history->contains(fn (LearningState $state): bool => $state->id === $stable->id));
        $this->assertGreaterThanOrEqual(3, $history->count());

        $this->assertDatabaseHas('learning_states', ['id' => $needsSupport->id, 'state' => LearningStateValue::NeedsSupport->value]);
        $this->assertDatabaseHas('learning_states', ['id' => $progressing->id, 'state' => LearningStateValue::Progressing->value]);
        $this->assertDatabaseHas('learning_states', ['id' => $stable->id, 'state' => LearningStateValue::Stable->value]);
    }

    public function test_chronological_ordering_of_evidence_and_states(): void
    {
        $early = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ], now()->subMinutes(30));
        $mid = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ], now()->subMinutes(20));
        $late = $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ], now()->subMinutes(10));

        $timeline = $this->query->evidenceTimeline($this->student->id, $this->activity->id);
        $eventIds = $timeline->pluck('learning_event_id')->unique()->values()->all();

        $this->assertSame([$early->id, $mid->id, $late->id], $eventIds);

        $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        // Force a second historical state with additional later evidence.
        $this->record('code_run', ['status' => 'success'], now()->subMinutes(5));
        $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);

        $states = $this->query->learningStateHistory($this->student->id, $this->activity->id);
        $this->assertGreaterThanOrEqual(2, $states->count());

        for ($i = 1; $i < $states->count(); $i++) {
            $previous = $states[$i - 1]->inferred_at;
            $current = $states[$i]->inferred_at;
            $this->assertTrue(
                $previous->lte($current),
                'Learning states must be chronologically ordered by inferred_at.',
            );
        }
    }

    public function test_research_context_preserves_available_fields_without_inventing_gaps(): void
    {
        $context = $this->query->researchContextForActivity($this->activity, $this->student->id);

        $this->assertSame($this->course->id, $context['course_id']);
        $this->assertSame('Research Course Alpha', $context['course_title']);
        $this->assertSame($this->module->id, $context['module_id']);
        $this->assertSame($this->unit->id, $context['learning_unit_id']);
        $this->assertSame($this->activity->id, $context['activity_id']);
        $this->assertSame('python-m5-01', $context['programming_language']);
        $this->assertSame('Python M5-01', $context['programming_language_display']);
        $this->assertSame(BloomLevel::Apply->value, $context['bloom_demand']);
        $this->assertSame(DaveLevel::Manipulation->value, $context['dave_demand']);
        $this->assertSame('task_demand', $context['bloom_semantics']);
        $this->assertNull($context['campus']);
        $this->assertNull($context['institution']);
        $this->assertNull($context['cohort']);
        $this->assertNotSame('python', $context['programming_language']); // no hard-coded invent
        $this->assertSame(
            $this->query->researchLearnerId($this->student->id),
            $context['research_learner_id'],
        );
    }

    public function test_evidence_quality_and_confidence_are_preserved(): void
    {
        $event = $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $evidence = $event->validatedEvidence()->firstOrFail();

        $beforeQuality = $evidence->quality;
        $beforeConfidence = $evidence->confidence;

        $provenance = $this->query->evidenceProvenance($evidence->fresh());
        $assembled = $this->query->assembleForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($this->enumValue($beforeQuality), $provenance['quality']);
        $this->assertSame($this->enumValue($beforeConfidence), $provenance['confidence']);
        $this->assertSame($this->enumValue($beforeQuality), $assembled['validated_evidence'][0]['quality']);
        $this->assertSame($this->enumValue($beforeConfidence), $assembled['validated_evidence'][0]['confidence']);

        $fresh = $evidence->fresh();
        $this->assertSame($beforeQuality, $fresh->quality);
        $this->assertSame($beforeConfidence, $fresh->confidence);
    }

    public function test_bloom_and_dave_remain_task_demand_not_learner_capability(): void
    {
        $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $provenance = $this->query->learningStateProvenance($state);
        $context = $this->query->researchContextForActivity($this->activity, $this->student->id);

        $this->assertSame(BloomLevel::Apply->value, $provenance['bloom_demand']);
        $this->assertSame(DaveLevel::Manipulation->value, $provenance['dave_demand']);
        $this->assertSame('task_demand', $provenance['bloom_semantics']);
        $this->assertSame('task_demand', $provenance['dave_semantics']);
        $this->assertSame('task_demand', $context['bloom_semantics']);
        $this->assertArrayNotHasKey('learner_bloom_level', $provenance);
        $this->assertArrayNotHasKey('learner_dave_level', $provenance);
    }

    public function test_m5_01_does_not_mutate_m4_records_or_create_m4_artifacts(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $intervention = $this->interventionService->createForLearningState($state);
        $action = $this->nextActionService->decideForLearningState($state, $intervention);

        $countsBefore = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ];
        $stateSnapshot = $state->fresh()->only([
            'state', 'state_confidence', 'inference_rule', 'explanation', 'inference_key',
        ]);
        $interventionSnapshot = $intervention->fresh()->only([
            'intervention_type', 'selection_rule', 'reason', 'intervention_key',
        ]);
        $actionSnapshot = $action->fresh()->only([
            'action', 'decision_rule', 'reason', 'decision_key',
        ]);

        $this->query->assembleForLearnerActivity($this->student->id, $this->activity->id);
        $this->query->closedLoopTrace($state->fresh());
        $this->query->learningStateHistory($this->student->id);
        $this->query->evidenceTimeline($this->student->id);

        $this->assertSame($countsBefore, [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
        ]);
        $this->assertSame($stateSnapshot, $state->fresh()->only(array_keys($stateSnapshot)));
        $this->assertSame($interventionSnapshot, $intervention->fresh()->only(array_keys($interventionSnapshot)));
        $this->assertSame($actionSnapshot, $action->fresh()->only(array_keys($actionSnapshot)));

        $this->assertFalse(Schema::hasTable('research_evidence'));
        $this->assertFalse(Schema::hasTable('research_evidence_archive'));
        $this->assertFalse(Schema::hasTable('nexus_cycles'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LongitudinalAnalyticsService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningTrajectoryService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\WeakAreaDetectionService'));
    }

    public function test_closed_loop_trace_uses_existing_fk_chain(): void
    {
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $intervention = $this->interventionService->createForLearningState($state);
        $action = $this->nextActionService->decideForLearningState($state, $intervention);

        $trace = $this->query->closedLoopTrace($state->fresh([
            'validatedEvidence.learningEvent',
            'adaptiveInterventions',
            'nextLearningActions',
            'activity',
        ]));

        $this->assertSame($state->id, $trace['learning_state']['learning_state_id']);
        $this->assertSame($intervention->id, $trace['adaptive_interventions'][0]['adaptive_intervention_id']);
        $this->assertSame($action->id, $trace['next_learning_actions'][0]['next_learning_action_id']);
        $this->assertFalse($trace['creates_research_copy']);
        $this->assertFalse($trace['performs_longitudinal_analysis']);
        $this->assertFalse($trace['mutates_m4']);
        $this->assertNull($trace['research_context']['campus']);
        $this->assertSame('python-m5-01', $trace['research_context']['programming_language']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(string $eventType, array $payload = [], $occurredAt = null): LearningEvent
    {
        $event = LearningEvent::record(
            $eventType,
            $this->student->id,
            $this->course->id,
            $this->activity->id,
            $payload,
        );

        if ($occurredAt !== null) {
            $event->forceFill(['occurred_at' => $occurredAt])->save();
        }

        return $event->fresh(['validatedEvidence']);
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof \UnitEnum ? $value->value : (is_string($value) ? $value : null);
    }
}
