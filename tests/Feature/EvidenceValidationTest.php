<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\ExecutionAnomaly;
use App\Enums\TaskRepetition;
use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\CodeExecution;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LanguageExecutionProfile;
use App\Models\LearningEvent;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ProgrammingActivity;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Execution\ProgrammingActivityService;
use App\Services\Research\EvidenceValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EvidenceValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private LanguageExecutionProfile $profile;

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
        ]);
        $this->profile = LanguageExecutionProfile::query()->create([
            'identifier' => 'python-t02',
            'display_name' => 'Python T02',
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
    }

    public function test_performance_classification(): void
    {
        $event = $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);

        $evidence = $this->primaryEvidence($event);

        $this->assertSame(EvidenceCategory::Performance, $evidence->evidence_category);
        $this->assertSame('submission_accepted', $evidence->evidence_type);
        $this->assertSame('Submission accepted', $evidence->observed_value['summary']);
        $this->assertSame($event->event_type, 'submission_accepted');
    }

    public function test_behavioral_classification(): void
    {
        $first = $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);
        $this->assertSame(1, ValidatedEvidence::query()->where('learning_event_id', $first->id)->count());
        $this->assertNull(
            ValidatedEvidence::query()
                ->where('learning_event_id', $first->id)
                ->where('evidence_category', EvidenceCategory::Behavioral)
                ->first()
        );

        $second = $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);

        $records = ValidatedEvidence::query()
            ->where('learning_event_id', $second->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $records);
        $this->assertSame(1, LearningEvent::query()->whereKey($second->id)->count());
        $this->assertTrue($records->every(fn (ValidatedEvidence $evidence): bool => $evidence->learning_event_id === $second->id));
        $this->assertSame(
            [EvidenceCategory::Performance->value, EvidenceCategory::Behavioral->value],
            $records->pluck('evidence_category')->map->value->all()
        );

        $behavioral = $records->firstWhere('evidence_category', EvidenceCategory::Behavioral);
        $this->assertNotNull($behavioral);
        $this->assertSame('repeated_submission_failures', $behavioral->evidence_type);
        $this->assertSame('Repeated submission failures detected', $behavioral->observed_value['summary']);
        $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $behavioral->observed_value['summary']);
        $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $behavioral->validation_reason);
        $this->assertSame($this->primaryEvidence($second)->source_record_type, $behavioral->source_record_type);
        $this->assertSame($this->primaryEvidence($second)->source_record_id, $behavioral->source_record_id);
    }

    public function test_interaction_classification(): void
    {
        $started = $this->record('activity_started');
        $run = $this->record('code_run', ['status' => 'success']);
        $submit = $this->record('code_submit', ['status' => 'success']);

        $this->assertSame(EvidenceCategory::Interaction, $this->primaryEvidence($started)->evidence_category);
        $this->assertSame('activity_started', $this->primaryEvidence($started)->evidence_type);
        $this->assertSame(EvidenceCategory::Interaction, $this->primaryEvidence($run)->evidence_category);
        $this->assertSame('code_run', $this->primaryEvidence($run)->evidence_type);
        $this->assertSame(EvidenceCategory::Interaction, $this->primaryEvidence($submit)->evidence_category);
        $this->assertSame('code_submit', $this->primaryEvidence($submit)->evidence_type);
    }

    public function test_system_context_classification(): void
    {
        $execution = $this->execution(['status' => 'timeout', 'timeout' => true]);
        $event = $this->record('code_run', [
            'execution_id' => $execution->id,
            'status' => 'timeout',
        ]);

        $evidence = $this->primaryEvidence($event);

        $this->assertSame(EvidenceCategory::SystemContext, $evidence->evidence_category);
        $this->assertSame('execution_timeout', $evidence->evidence_type);
        $this->assertSame('Execution timeout detected', $evidence->observed_value['summary']);
        $this->assertSame('code_run', $event->event_type);
        $this->assertSame(1, LearningEvent::query()->whereKey($event->id)->count());
        $this->assertNotNull(
            ValidatedEvidence::query()
                ->where('learning_event_id', $event->id)
                ->where('evidence_category', EvidenceCategory::SystemContext)
                ->first()
        );
    }

    public function test_submission_rejected_without_anomaly_is_performance(): void
    {
        $event = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $performance = ValidatedEvidence::query()
            ->where('learning_event_id', $event->id)
            ->where('evidence_category', EvidenceCategory::Performance)
            ->first();

        $this->assertNotNull($performance);
        $this->assertSame('submission_rejected', $performance->evidence_type);
        $this->assertSame($event->id, $performance->learning_event_id);
        $this->assertNull(
            ValidatedEvidence::query()
                ->where('learning_event_id', $event->id)
                ->where('evidence_category', EvidenceCategory::SystemContext)
                ->first()
        );
        $this->assertSame(1, LearningEvent::query()->count());
    }

    public function test_submission_rejected_with_runtime_anomaly_keeps_performance_and_system_context(): void
    {
        $execution = $this->execution([
            'status' => 'runtime_error',
            'timeout' => false,
            'runtime_error' => 'ZeroDivisionError',
        ]);
        $event = $this->record('submission_rejected', [
            'execution_id' => $execution->id,
            'status' => 'runtime_error',
            'passes_evaluation' => false,
        ]);

        $records = ValidatedEvidence::query()
            ->where('learning_event_id', $event->id)
            ->orderBy('id')
            ->get();

        $this->assertSame(1, LearningEvent::query()->whereKey($event->id)->count());
        $this->assertTrue($records->every(fn (ValidatedEvidence $evidence): bool => $evidence->learning_event_id === $event->id));

        $performance = $records->firstWhere('evidence_category', EvidenceCategory::Performance);
        $anomaly = $records->firstWhere('evidence_category', EvidenceCategory::SystemContext);

        $this->assertNotNull($performance);
        $this->assertSame('submission_rejected', $performance->evidence_type);
        $this->assertSame('Submission rejected', $performance->observed_value['summary']);
        $this->assertSame(EvidenceQuality::Uncertain, $performance->quality);
        $this->assertSame(EvidenceConfidence::Low, $performance->confidence);
        $this->assertStringContainsString('Execution anomaly: detected.', $performance->validation_reason);

        $this->assertNotNull($anomaly);
        $this->assertSame('execution_runtime_failure', $anomaly->evidence_type);
        $this->assertSame('Execution runtime failure detected', $anomaly->observed_value['summary']);
        $this->assertSame($execution->id, $anomaly->source_record_id);
        $this->assertSame('code_execution', $anomaly->source_record_type);
        $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $performance->validation_reason);
        $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $anomaly->validation_reason);
    }

    public function test_submission_accepted_with_runtime_anomaly_keeps_performance_and_system_context(): void
    {
        $execution = $this->execution([
            'status' => 'runtime_error',
            'timeout' => false,
            'runtime_error' => 'ZeroDivisionError',
        ]);
        $event = $this->record('submission_accepted', [
            'execution_id' => $execution->id,
            'status' => 'runtime_error',
            'passes_evaluation' => true,
        ]);

        $performance = ValidatedEvidence::query()
            ->where('learning_event_id', $event->id)
            ->where('evidence_category', EvidenceCategory::Performance)
            ->first();
        $anomaly = ValidatedEvidence::query()
            ->where('learning_event_id', $event->id)
            ->where('evidence_category', EvidenceCategory::SystemContext)
            ->first();

        $this->assertNotNull($performance);
        $this->assertSame('submission_accepted', $performance->evidence_type);
        $this->assertSame(EvidenceQuality::Uncertain, $performance->quality);
        $this->assertSame(EvidenceConfidence::Low, $performance->confidence);
        $this->assertNotNull($anomaly);
        $this->assertSame('execution_runtime_failure', $anomaly->evidence_type);
        $this->assertSame($event->id, $performance->learning_event_id);
        $this->assertSame($event->id, $anomaly->learning_event_id);
        $this->assertSame(1, LearningEvent::query()->count());
    }

    public function test_new_task(): void
    {
        $event = $this->record('activity_started');
        $evidence = $this->primaryEvidence($event);

        $this->assertSame(TaskRepetition::New->value, $evidence->context_summary['task_repetition']);
        $this->assertStringContainsString('Task exposure is new.', $evidence->validation_reason);
    }

    public function test_code_run_after_activity_started_remains_new(): void
    {
        $this->record('activity_started');
        $event = $this->record('code_run', ['status' => 'success']);
        $evidence = $this->primaryEvidence($event);

        $this->assertSame(TaskRepetition::New->value, $evidence->context_summary['task_repetition']);
        $this->assertStringContainsString('Task exposure is new.', $evidence->validation_reason);
        $this->assertSame(2, LearningEvent::query()->count());
        $this->assertSame('code_run', $event->event_type);
    }

    public function test_first_attempt_through_submit_remains_new(): void
    {
        $started = $this->record('activity_started');
        $run = $this->record('code_run', ['status' => 'success']);
        $submit = $this->record('code_submit', ['status' => 'success']);

        foreach ([$started, $run, $submit] as $event) {
            $this->assertSame(
                TaskRepetition::New->value,
                $this->primaryEvidence($event)->context_summary['task_repetition'],
                $event->event_type.' must remain new before any subsequent attempt',
            );
        }
    }

    public function test_activity_completed_after_first_rejection_remains_new(): void
    {
        $this->record('activity_started');
        $this->record('code_run', ['status' => 'success']);
        $this->record('code_submit', ['status' => 'success']);
        $rejected = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $completed = $this->record('activity_completed');

        $this->assertSame(TaskRepetition::New->value, $this->primaryEvidence($rejected)->context_summary['task_repetition']);
        $this->assertSame(TaskRepetition::New->value, $this->primaryEvidence($completed)->context_summary['task_repetition']);
        $this->assertSame('activity_completed', $completed->event_type);
        $this->assertSame($rejected->id + 1, $completed->id);
    }

    public function test_activity_completed_after_first_acceptance_remains_new(): void
    {
        $this->record('activity_started');
        $accepted = $this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]);
        $completed = $this->record('activity_completed');

        $this->assertSame(TaskRepetition::New->value, $this->primaryEvidence($accepted)->context_summary['task_repetition']);
        $this->assertSame(TaskRepetition::New->value, $this->primaryEvidence($completed)->context_summary['task_repetition']);
        $this->assertSame('submission_accepted', $accepted->event_type);
        $this->assertSame('activity_completed', $completed->event_type);
    }

    public function test_subsequent_attempt_after_completed_first_attempt_is_repeated(): void
    {
        $this->record('activity_started');
        $this->record('code_run', ['status' => 'success']);
        $this->record('code_submit', ['status' => 'success']);
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $completed = $this->record('activity_completed');
        $this->assertSame(TaskRepetition::New->value, $this->primaryEvidence($completed)->context_summary['task_repetition']);

        $secondRun = $this->record('code_run', ['status' => 'success']);
        $secondSubmit = $this->record('code_submit', ['status' => 'success']);

        $this->assertSame(TaskRepetition::Repeated->value, $this->primaryEvidence($secondRun)->context_summary['task_repetition']);
        $this->assertSame(TaskRepetition::Repeated->value, $this->primaryEvidence($secondSubmit)->context_summary['task_repetition']);
        $this->assertSame('code_run', $secondRun->event_type);
        $this->assertSame('code_submit', $secondSubmit->event_type);
    }

    public function test_interaction_within_first_exposure_remains_new(): void
    {
        $started = $this->record('activity_started');
        $run = $this->record('code_run', ['status' => 'success']);
        $submit = $this->record('code_submit', ['status' => 'success']);
        $completed = $this->record('activity_completed');

        foreach ([$started, $run, $submit, $completed] as $event) {
            $this->assertSame(
                TaskRepetition::New->value,
                $this->primaryEvidence($event)->context_summary['task_repetition'],
                $event->event_type.' must remain new within the first exposure',
            );
        }
    }

    public function test_repeated_task(): void
    {
        $this->record('activity_started');
        $this->record('code_run', ['status' => 'success']);
        $firstAttempt = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $this->assertSame(
            TaskRepetition::New->value,
            $this->primaryEvidence($firstAttempt)->context_summary['task_repetition'],
        );

        $secondRun = $this->record('code_run', ['status' => 'success']);
        $secondAttempt = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $this->assertSame(
            TaskRepetition::Repeated->value,
            $this->primaryEvidence($secondRun)->context_summary['task_repetition'],
        );
        $this->assertSame(
            TaskRepetition::Repeated->value,
            $this->primaryEvidence($secondAttempt)->context_summary['task_repetition'],
        );
        $this->assertStringContainsString(
            'Task exposure is repeated.',
            $this->primaryEvidence($secondAttempt)->validation_reason,
        );
        $this->assertSame('submission_rejected', $secondAttempt->event_type);
        $this->assertSame(2, LearningEvent::query()->where('event_type', 'submission_rejected')->count());
    }

    public function test_unknown_repetition(): void
    {
        $event = LearningEvent::record(
            'code_run',
            $this->student->id,
            $this->course->id,
            null,
            ['status' => 'success'],
        );
        $evidence = $this->primaryEvidence($event);

        $this->assertNull($event->activity_id);
        $this->assertSame(TaskRepetition::Unknown->value, $evidence->context_summary['task_repetition']);
        $this->assertSame(EvidenceQuality::Uncertain, $evidence->quality);
    }

    public function test_known_difficulty(): void
    {
        $event = $this->record('activity_started');
        $evidence = $this->primaryEvidence($event);

        $this->assertSame('medium', $this->activity->getDifficulty());
        $this->assertSame('medium', $evidence->context_summary['task_difficulty']);
        $this->assertStringContainsString('Task difficulty: medium.', $evidence->validation_reason);
    }

    public function test_unknown_difficulty(): void
    {
        $this->activity->update(['difficulty' => null]);

        $event = $this->record('activity_started');
        $evidence = $this->primaryEvidence($event);

        $this->assertNull($this->activity->fresh()->getDifficulty());
        $this->assertSame('unknown', $evidence->context_summary['task_difficulty']);
        $this->assertStringContainsString('Task difficulty: unknown.', $evidence->validation_reason);
    }

    public function test_execution_anomaly_detected(): void
    {
        $execution = $this->execution([
            'status' => 'runtime_error',
            'timeout' => false,
            'runtime_error' => 'ZeroDivisionError',
        ]);
        $event = $this->record('code_run', [
            'execution_id' => $execution->id,
            'status' => 'runtime_error',
        ]);
        $evidence = $this->primaryEvidence($event);

        $this->assertSame(ExecutionAnomaly::Detected->value, $evidence->context_summary['execution_anomaly']);
        $this->assertSame(EvidenceQuality::Uncertain, $evidence->quality);
        $this->assertSame(EvidenceConfidence::Low, $evidence->confidence);
        $this->assertStringContainsString('Execution anomaly: detected.', $evidence->validation_reason);
    }

    public function test_execution_anomaly_absent(): void
    {
        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $event = $this->record('code_run', [
            'execution_id' => $execution->id,
            'status' => 'success',
        ]);
        $evidence = $this->primaryEvidence($event);

        $this->assertSame(ExecutionAnomaly::None->value, $evidence->context_summary['execution_anomaly']);
        $this->assertSame(EvidenceQuality::Valid, $evidence->quality);
        $this->assertStringContainsString('Execution anomaly: none.', $evidence->validation_reason);
    }

    public function test_missing_network_environment_telemetry_is_unknown(): void
    {
        $event = $this->record('activity_started');
        $evidence = $this->primaryEvidence($event);

        $this->assertSame('unknown', $evidence->context_summary['network_environment']);
        $this->assertArrayNotHasKey('network_normal', $evidence->context_summary);
        $this->assertStringContainsString('Network/environment telemetry is unknown.', $evidence->validation_reason);
    }

    public function test_quality_classification(): void
    {
        $valid = $this->primaryEvidence($this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]));
        $this->assertSame(EvidenceQuality::Valid, $valid->quality);

        $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);
        $contextDependent = $this->primaryEvidence($this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]));
        $this->assertSame(EvidenceQuality::ContextDependent, $contextDependent->quality);

        $uncertain = $this->primaryEvidence($this->record('code_run', ['status' => 'timeout']));
        $this->assertSame(EvidenceQuality::Uncertain, $uncertain->quality);

        $this->assertContains($valid->quality->value, ['valid', 'uncertain', 'context_dependent']);
        $this->assertContains($contextDependent->quality->value, ['valid', 'uncertain', 'context_dependent']);
        $this->assertContains($uncertain->quality->value, ['valid', 'uncertain', 'context_dependent']);
    }

    public function test_confidence_classification(): void
    {
        $high = $this->primaryEvidence($this->record('submission_accepted', [
            'status' => 'success',
            'passes_evaluation' => true,
        ]));
        $this->assertSame(EvidenceConfidence::High, $high->confidence);
        $this->assertStringContainsString('evidence validity/usefulness', $high->validation_reason);
        $this->assertStringContainsString('not for a psychological or learning state', $high->validation_reason);

        $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);
        $medium = $this->primaryEvidence($this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]));
        $this->assertSame(EvidenceConfidence::Medium, $medium->confidence);

        $low = $this->primaryEvidence($this->record('code_run', ['status' => 'system_error']));
        $this->assertSame(EvidenceConfidence::Low, $low->confidence);

        $this->assertContains($high->confidence->value, ['high', 'medium', 'low']);
        $this->assertContains($medium->confidence->value, ['high', 'medium', 'low']);
        $this->assertContains($low->confidence->value, ['high', 'medium', 'low']);
    }

    public function test_provenance(): void
    {
        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $event = $this->record('submission_rejected', [
            'execution_id' => $execution->id,
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $evidence = $this->primaryEvidence($event);

        $this->assertSame($event->id, $evidence->learning_event_id);
        $this->assertTrue($evidence->learningEvent->is($event));
        $this->assertSame($this->student->id, $evidence->user_id);
        $this->assertSame($this->activity->id, $evidence->activity_id);
        $this->assertSame('code_execution', $evidence->source_record_type);
        $this->assertSame($execution->id, $evidence->source_record_id);
        $this->assertSame('submission_rejected', $event->event_type);
        $this->assertNotNull($evidence->validated_at);
        $this->assertTrue($evidence->validated_at->equalTo($event->occurred_at) || $evidence->validated_at->gte($event->occurred_at));
    }

    public function test_validation_explanation(): void
    {
        $this->record('activity_started');
        $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $event = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $evidence = $this->primaryEvidence($event);

        $this->assertNotSame('', $evidence->validation_reason);
        $this->assertStringContainsString('Observed: submission_rejected.', $evidence->validation_reason);
        $this->assertStringContainsString('Task exposure is repeated.', $evidence->validation_reason);
        $this->assertStringContainsString('Execution anomaly: none.', $evidence->validation_reason);
        $this->assertStringContainsString('Task difficulty: medium.', $evidence->validation_reason);
        $this->assertStringContainsString('Quality is context_dependent', $evidence->validation_reason);
        $this->assertStringContainsString('repeated exposure to the task requires contextual interpretation', $evidence->validation_reason);
        $this->assertSame(EvidenceQuality::ContextDependent, $evidence->quality);
        $this->assertSame(EvidenceConfidence::Medium, $evidence->confidence);
    }

    public function test_no_learning_state_inference(): void
    {
        $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);
        $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);
        $third = $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);

        foreach (ValidatedEvidence::query()->get() as $evidence) {
            $this->assertArrayNotHasKey('learning_state', $evidence->getAttributes());
            $this->assertArrayNotHasKey('cognitive_state', $evidence->getAttributes());
            $this->assertArrayNotHasKey('affective_state', $evidence->getAttributes());
            $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $evidence->observed_value['summary']);
            $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $evidence->validation_reason);
            $this->assertDoesNotMatchRegularExpression('/\bconfused\b|\bfrustrated\b|\bdemotivated\b/i', $evidence->validation_reason);
        }

        $behavioral = ValidatedEvidence::query()
            ->where('learning_event_id', $third->id)
            ->where('evidence_category', EvidenceCategory::Behavioral)
            ->first();
        $this->assertSame('Repeated submission failures detected', $behavioral->observed_value['summary']);

        // M4-T02 must not embed learning-state fields on validated evidence itself.
        $this->assertFalse(Schema::hasColumn('validated_evidence', 'learning_state'));
        $this->assertFalse(Schema::hasColumn('validated_evidence', 'cognitive_state'));
        $this->assertFalse(Schema::hasColumn('validated_evidence', 'affective_state'));

        // Recording/validating evidence alone must not create a Learning State row.
        // Learning State inference belongs to M4-T03 and is invoked explicitly.
        $this->assertSame(0, \App\Models\LearningState::query()->count());
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningStateManager'));
        $this->assertFalse(class_exists('App\\Services\\Research\\EvidenceFusionService'));
    }

    public function test_m3_submit_sequence_does_not_emit_uppercase_or_false_repetition(): void
    {
        $this->record('code_submit', [
            'execution_id' => 11,
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $rejected = $this->record('submission_rejected', [
            'execution_id' => 11,
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $this->assertSame(0, LearningEvent::query()->where('event_type', 'SUBMISSION_REJECTED')->count());
        $this->assertSame(0, LearningEvent::query()->where('event_type', 'SUBMISSION_ACCEPTED')->count());
        $this->assertSame(1, LearningEvent::query()->where('event_type', 'submission_rejected')->count());
        $this->assertSame(EvidenceCategory::Performance, $this->primaryEvidence($rejected)->evidence_category);
        $this->assertNull(
            ValidatedEvidence::query()
                ->where('learning_event_id', $rejected->id)
                ->where('evidence_category', EvidenceCategory::Behavioral)
                ->first()
        );
    }

    public function test_uppercase_outcome_event_is_classified_without_creating_a_second_raw_event(): void
    {
        $event = $this->record('SUBMISSION_REJECTED', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $this->assertSame('SUBMISSION_REJECTED', $event->event_type);
        $this->assertSame(1, LearningEvent::query()->count());
        $this->assertSame(1, ValidatedEvidence::query()->where('learning_event_id', $event->id)->count());
        $this->assertSame(EvidenceCategory::Performance, $this->primaryEvidence($event)->evidence_category);
        $this->assertSame('submission_rejected', $this->primaryEvidence($event)->evidence_type);
    }

    public function test_uppercase_prior_rejection_counts_toward_behavioral_repetition(): void
    {
        $this->record('SUBMISSION_REJECTED', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);
        $second = $this->record('submission_rejected', [
            'status' => 'success',
            'passes_evaluation' => false,
        ]);

        $behavioral = ValidatedEvidence::query()
            ->where('learning_event_id', $second->id)
            ->where('evidence_category', EvidenceCategory::Behavioral)
            ->first();

        $this->assertNotNull($behavioral);
        $this->assertSame('repeated_submission_failures', $behavioral->evidence_type);
        $this->assertSame($second->id, $behavioral->learning_event_id);
        $this->assertSame(
            1,
            LearningEvent::query()->where('event_type', 'submission_rejected')->count()
        );
        $this->assertSame(
            1,
            LearningEvent::query()->where('event_type', 'SUBMISSION_REJECTED')->count()
        );
    }

    public function test_submission_accepted_evidence_traces_to_activity_submission(): void
    {
        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $submission = $this->submission();

        $event = $this->record('submission_accepted', [
            'execution_id' => $execution->id,
            'status' => 'success',
            'language' => 'python-t02',
            'passes_evaluation' => true,
            'test_summary' => ['passed' => 1, 'total' => 1],
            'submission_id' => $submission->id,
            'attempt_number' => $submission->attempt_number,
        ]);

        $evidence = $this->primaryEvidence($event);

        $this->assertSame($event->id, $evidence->learning_event_id);
        $this->assertSame('activity_submission', $evidence->source_record_type);
        $this->assertSame($submission->id, $evidence->source_record_id);
        $this->assertSame($execution->id, $event->payload['execution_id']);
        $this->assertSame('success', $event->payload['status']);
        $this->assertTrue($event->payload['passes_evaluation']);
        $this->assertSame(1, LearningEvent::query()->where('event_type', 'submission_accepted')->count());
    }

    public function test_submission_rejected_evidence_traces_to_activity_submission(): void
    {
        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $submission = $this->submission();

        $event = $this->record('submission_rejected', [
            'execution_id' => $execution->id,
            'status' => 'success',
            'language' => 'python-t02',
            'passes_evaluation' => false,
            'test_summary' => ['passed' => 0, 'total' => 1],
            'submission_id' => $submission->id,
            'attempt_number' => $submission->attempt_number,
        ]);

        $evidence = $this->primaryEvidence($event);

        $this->assertSame($event->id, $evidence->learning_event_id);
        $this->assertSame('activity_submission', $evidence->source_record_type);
        $this->assertSame($submission->id, $evidence->source_record_id);
        $this->assertSame($execution->id, $event->payload['execution_id']);
        $this->assertSame(['passed' => 0, 'total' => 1], $event->payload['test_summary']);
        $this->assertSame(1, LearningEvent::query()->count());
    }

    public function test_submission_outcome_is_validated_once_with_complete_provenance(): void
    {
        $counter = new class
        {
            public int $count = 0;
        };
        $inner = app(EvidenceValidationService::class);
        $this->app->instance(EvidenceValidationService::class, new class($inner, $counter)
        {
            public function __construct(
                private EvidenceValidationService $inner,
                private object $counter,
            ) {}

            public function validateEvent(LearningEvent $event)
            {
                $this->counter->count++;

                return $this->inner->validateEvent($event);
            }
        });

        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $submission = $this->submission();
        $programmingActivity = ProgrammingActivity::query()->create([
            'activity_id' => $this->activity->id,
            'language_execution_profile_id' => $this->profile->id,
        ]);

        $event = app(ProgrammingActivityService::class)->recordSubmissionOutcome(
            $this->student,
            $programmingActivity,
            [
                'execution_id' => $execution->id,
                'status' => 'success',
                'language' => 'python-t02',
                'passes_evaluation' => false,
                'test_summary' => ['passed' => 0, 'total' => 1],
            ],
            $submission,
        );

        $this->assertSame(1, $counter->count);
        $this->assertSame('submission_rejected', $event->event_type);
        $this->assertSame($submission->id, $event->payload['submission_id']);
        $this->assertSame($execution->id, $event->payload['execution_id']);
        $this->assertSame('python-t02', $event->payload['language']);
        $this->assertSame($submission->attempt_number, $event->payload['attempt_number']);
        $this->assertSame(1, LearningEvent::query()->whereIn('event_type', ['submission_accepted', 'submission_rejected'])->count());
        $this->assertSame('activity_submission', $this->primaryEvidence($event)->source_record_type);
        $this->assertSame($submission->id, $this->primaryEvidence($event)->source_record_id);
    }

    public function test_reattaching_submission_provenance_does_not_duplicate_validated_evidence(): void
    {
        $execution = $this->execution(['status' => 'success', 'timeout' => false]);
        $submission = $this->submission();
        $event = $this->record('submission_rejected', [
            'execution_id' => $execution->id,
            'status' => 'success',
            'passes_evaluation' => false,
            'submission_id' => $submission->id,
            'attempt_number' => $submission->attempt_number,
        ]);

        $this->assertSame(1, ValidatedEvidence::query()->where('learning_event_id', $event->id)->count());
        $this->assertSame('activity_submission', $this->primaryEvidence($event)->source_record_type);
        $this->assertSame($submission->id, $this->primaryEvidence($event)->source_record_id);

        app(EvidenceValidationService::class)->validateEvent($event);

        $this->assertSame(1, ValidatedEvidence::query()->where('learning_event_id', $event->id)->count());
        $evidence = $this->primaryEvidence($event->fresh());
        $this->assertSame('activity_submission', $evidence->source_record_type);
        $this->assertSame($submission->id, $evidence->source_record_id);
        $this->assertSame($execution->id, $event->fresh()->payload['execution_id']);
        $this->assertSame($event->id, $evidence->learning_event_id);
    }

    public function test_m3_compatibility(): void
    {
        $run = $this->record('code_run', ['status' => 'success']);
        $submit = $this->record('code_submit', ['status' => 'success', 'passes_evaluation' => true]);
        $accepted = $this->record('submission_accepted', ['status' => 'success', 'passes_evaluation' => true]);
        $rejected = $this->record('submission_rejected', ['status' => 'success', 'passes_evaluation' => false]);

        $this->assertSame('code_run', $run->event_type);
        $this->assertSame('code_submit', $submit->event_type);
        $this->assertSame('submission_accepted', $accepted->event_type);
        $this->assertSame('submission_rejected', $rejected->event_type);

        $this->assertSame(
            ['code_run', 'code_submit', 'submission_accepted', 'submission_rejected'],
            LearningEvent::query()->orderBy('id')->pluck('event_type')->all(),
        );

        $this->assertGreaterThanOrEqual(4, ValidatedEvidence::query()->count());
        $this->assertSame('code_run', $this->primaryEvidence($run)->learningEvent->event_type);
        $this->assertSame('code_submit', $this->primaryEvidence($submit)->learningEvent->event_type);
        $this->assertSame(EvidenceCategory::Performance, $this->primaryEvidence($accepted)->evidence_category);
        $this->assertSame(EvidenceCategory::Performance, $this->primaryEvidence($rejected)->evidence_category);
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function execution(array $attributes): CodeExecution
    {
        return CodeExecution::query()->create(array_merge([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'language_execution_profile_id' => $this->profile->id,
            'status' => 'success',
            'source_code' => 'print(1)',
            'timeout' => false,
        ], $attributes));
    }

    private function submission(): ActivitySubmission
    {
        $enrollment = Enrollment::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => \App\Enums\EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        return ActivitySubmission::factory()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'attempt_number' => 1,
        ]);
    }

    private function primaryEvidence(LearningEvent $event): ValidatedEvidence
    {
        $evidence = ValidatedEvidence::query()
            ->where('learning_event_id', $event->id)
            ->where('evidence_category', '!=', EvidenceCategory::Behavioral)
            ->first();

        $this->assertNotNull($evidence);

        return $evidence;
    }
}
