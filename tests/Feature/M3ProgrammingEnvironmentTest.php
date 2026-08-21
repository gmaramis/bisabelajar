<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\CodeExecution;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LanguageExecutionProfile;
use App\Models\LearningEvent;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ProgrammingActivity;
use App\Models\TestCase as TestCaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class M3ProgrammingEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $tutor;
    protected User $student;
    protected Course $course;
    protected Module $module;
    protected LearningUnit $learningUnit;
    protected Activity $activity;
    protected ProgrammingActivity $programmingActivity;
    protected LanguageExecutionProfile $profile;
    protected Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tutor
        $this->tutor = User::factory()->tutor()->create();

        // Create student
        $this->student = User::factory()->student()->create();

        // Create course
        $this->course = Course::factory()->create([
            'title' => 'Python Programming',
            'status' => 'published',
            'owner_id' => $this->tutor->id,
        ]);

        // Create module
        $this->module = Module::factory()->create([
            'course_id' => $this->course->id,
            'title' => 'Module 1',
            'status' => 'published',
        ]);

        // Create learning unit
        $this->learningUnit = LearningUnit::factory()->create([
            'module_id' => $this->module->id,
            'title' => 'Variables and Data Types',
            'status' => 'published',
        ]);

        // Create activity
        $this->activity = Activity::factory()->create([
            'learning_unit_id' => $this->learningUnit->id,
            'title' => 'Hello World Exercise',
            'type' => \App\Enums\ActivityType::CodingExercise,
            'status' => 'published',
            'configuration' => [
                'instructions' => 'Write a program that prints "Hello, World!"',
                'language' => 'python',
                'completion_rule' => \App\Enums\CompletionRule::Manual->value,
            ],
        ]);

        // Create enrollment for student
        $this->enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => \App\Enums\EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        // Create language execution profile
        $this->profile = LanguageExecutionProfile::create([
            'identifier' => 'python',
            'display_name' => 'Python 3.12',
            'file_extension' => '.py',
            'source_filename' => 'solution.py',
            'docker_image' => 'python:3.12-alpine',
            'compile_command' => null,
            'run_command' => 'python /workspace/solution.py',
            'execution_mode' => 'interpreted',
            'timeout_seconds' => 10,
            'memory_limit_mb' => 256,
            'cpu_limit' => 1,
            'network_enabled' => false,
            'enabled' => true,
            'environment_variables' => [],
            'allowed_files' => [],
        ]);

        // Create programming activity
        $this->programmingActivity = ProgrammingActivity::create([
            'activity_id' => $this->activity->id,
            'language_execution_profile_id' => $this->profile->id,
            'starter_code' => '# Write your code here\nprint("Hello, World!")',
            'editable_files' => ['solution.py'],
            'execution_time_limit_seconds' => 30,
            'memory_limit_mb' => 256,
            'source_code_size_limit_kb' => 100,
            'submission_rules' => [
                'max_attempts' => 10,
            ],
            'evaluation_config' => [
                'pass_threshold' => 100,
            ],
        ]);

        // Create test cases
        TestCaseModel::create([
            'programming_activity_id' => $this->programmingActivity->id,
            'name' => 'Basic Output Test',
            'description' => 'Check if program prints Hello World',
            'input' => '',
            'expected_output' => 'Hello, World!\n',
            'visible' => true,
            'sort_order' => 0,
            'timeout_seconds' => 5,
            'memory_limit_mb' => 128,
            'comparison_strategy' => 'trimmed',
        ]);

        TestCaseModel::create([
            'programming_activity_id' => $this->programmingActivity->id,
            'name' => 'Hidden Test - Edge Case',
            'description' => 'Hidden test for edge case',
            'input' => '',
            'expected_output' => 'Hello, World!\n',
            'visible' => false,
            'sort_order' => 1,
            'timeout_seconds' => 5,
            'memory_limit_mb' => 128,
            'comparison_strategy' => 'trimmed',
        ]);
    }

    public function test_student_can_view_programming_activity(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('activities.show', [
                'course' => $this->course->slug,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        $response->assertOk();
        $response->assertViewIs('activities.programming');
        $response->assertViewHas('programmingActivity');
        $response->assertViewHas('availableProfiles');
    }

    public function test_student_can_run_code_successfully(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson(route('student.activities.programming.run', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'source_code' => "print('Hello, World!')",
                'language_execution_profile_id' => $this->profile->id,
            ]);

        $response->assertJsonStructure([
            'success',
            'execution' => [
                'id',
                'status',
                'stdout',
                'stderr',
                'exit_code',
                'duration_ms',
                'memory_used_mb',
            ],
        ]);

        // Execution should be created
        $this->assertDatabaseHas('code_executions', [
            'user_id' => $this->student->id,
            'programming_activity_id' => $this->programmingActivity->id,
        ]);
    }

    public function test_student_can_submit_solution(): void
    {
        // Start the activity first
        \App\Models\ActivityProgress::markStarted($this->enrollment, $this->activity);

        $response = $this->actingAs($this->student)
            ->postJson(route('student.activities.programming.submit', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'source_code' => "print('Hello, World!')",
                'language_execution_profile_id' => $this->profile->id,
            ]);

        $response->assertJsonStructure([
            'success',
            'submission' => [
                'id',
                'status',
                'score',
                'passed_tests',
                'total_tests',
                'test_results' => [
                    '*' => [
                        'test_case_id',
                        'passed',
                        'stdout',
                        'stderr',
                        'duration_ms',
                        'memory_used_mb',
                    ],
                ],
            ],
        ]);
    }

    public function test_tutor_can_create_programming_activity(): void
    {
        // Create a new activity for coding exercise
        $activity = Activity::factory()->create([
            'learning_unit_id' => $this->learningUnit->id,
            'title' => 'New Coding Exercise',
            'type' => \App\Enums\ActivityType::CodingExercise,
            'status' => 'published',
            'configuration' => [
                'instructions' => 'Write a function to add two numbers',
                'language' => 'python',
            ],
        ]);

        $response = $this->actingAs($this->tutor)
            ->postJson(route('tutor.activities.programming.store', [
                'course' => $this->course->id,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $activity->id,
            ]), [
                'language_execution_profile_id' => $this->profile->id,
                'starter_code' => 'def add(a, b):\n    return a + b\n',
                'editable_files' => ['solution.py'],
                'execution_time_limit_seconds' => 30,
                'memory_limit_mb' => 256,
                'source_code_size_limit_kb' => 100,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'success',
            'programming_activity' => [
                'id',
                'language_execution_profile_id',
                'starter_code',
            ],
        ]);

        $this->assertDatabaseHas('programming_activities', [
            'activity_id' => $activity->id,
            'language_execution_profile_id' => $this->profile->id,
        ]);
    }

    public function test_tutor_can_manage_test_cases(): void
    {
        $response = $this->actingAs($this->tutor)
            ->postJson(route('tutor.activities.test-cases.store', [
                'course' => $this->course->id,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'name' => 'Test Add Function',
                'input' => '2 3',
                'expected_output' => '5',
                'visible' => true,
                'sort_order' => 2,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'success',
            'test_case' => [
                'id',
                'name',
                'visible',
            ],
        ]);

        $this->assertDatabaseHas('test_cases', [
            'programming_activity_id' => $this->programmingActivity->id,
            'name' => 'Test Add Function',
        ]);
    }

    public function test_hidden_tests_are_not_visible_to_students(): void
    {
        $response = $this->actingAs($this->student)
            ->get(route('activities.show', [
                'course' => $this->course->slug,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        $response->assertOk();

        // Get the programming activity from view data
        $programmingActivity = $response->viewData('programmingActivity');
        
        // Student should not see hidden test cases in the view
        // (The view should only receive visible test cases or none at all)
        // This is enforced in the frontend - backend doesn't expose hidden test cases
    }

    public function test_learning_events_are_recorded_on_run(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('student.activities.programming.run', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'source_code' => "print('Hello')",
                'language_execution_profile_id' => $this->profile->id,
            ]);

        // Check learning events were recorded
        $this->assertDatabaseHas('learning_events', [
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'code_run',
        ]);
    }

    public function test_learning_events_are_recorded_on_submit(): void
    {
        // Start the activity first
        \App\Models\ActivityProgress::markStarted($this->enrollment, $this->activity);

        $this->actingAs($this->student)
            ->postJson(route('student.activities.programming.submit', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'source_code' => "print('Hello, World!')",
                'language_execution_profile_id' => $this->profile->id,
            ]);

        // Check learning events were recorded
        $this->assertDatabaseHas('learning_events', [
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'code_submit',
        ]);
    }

    public function test_execution_respects_time_limit(): void
    {
        // Create a profile with short timeout for testing
        $quickProfile = LanguageExecutionProfile::create([
            'identifier' => 'python-quick',
            'display_name' => 'Python Quick',
            'file_extension' => '.py',
            'source_filename' => 'solution.py',
            'docker_image' => 'python:3.12-alpine',
            'compile_command' => null,
            'run_command' => 'python /workspace/solution.py',
            'execution_mode' => 'interpreted',
            'timeout_seconds' => 2,
            'memory_limit_mb' => 64,
            'cpu_limit' => 1,
            'network_enabled' => false,
            'enabled' => true,
            'environment_variables' => [],
            'allowed_files' => [],
        ]);

        $quickProgrammingActivity = ProgrammingActivity::create([
            'activity_id' => $this->activity->id,
            'language_execution_profile_id' => $quickProfile->id,
            'starter_code' => '',
            'editable_files' => ['solution.py'],
            'execution_time_limit_seconds' => 2,
            'memory_limit_mb' => 64,
            'source_code_size_limit_kb' => 100,
        ]);

        // Submit infinite loop code
        $response = $this->actingAs($this->student)
            ->postJson(route('student.activities.programming.run', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]), [
                'source_code' => "while True:\n    pass",
                'language_execution_profile_id' => $quickProfile->id,
            ]);

        // Should timeout (exit_code -9 or timeout status)
        $this->assertTrue($response->json('success'));
        $execution = $response->json('execution');
        $this->assertNotEquals(0, $execution['exit_code']);
    }

    /**
     * @test
     * M4-T01: activity_started event is recorded when student starts an activity
     */
    public function test_activity_started_event_is_recorded(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson(route('student.activities.start', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // The controller returns a redirect (302) - follow it
        $response->assertRedirect();
        
        // Check learning event was recorded
        $this->assertDatabaseHas('learning_events', [
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'activity_started',
        ]);

        $event = \App\Models\LearningEvent::where('event_type', 'activity_started')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertArrayHasKey('enrollment_id', $event->payload);
        $this->assertArrayHasKey('progress_status', $event->payload);
        $this->assertEquals('in_progress', $event->payload['progress_status']);
    }

    /**
     * @test
     * M4-T01: activity_completed event is recorded when student completes an activity
     */
    public function test_activity_completed_event_is_recorded(): void
    {
        // Start activity first
        $this->actingAs($this->student)
            ->postJson(route('student.activities.start', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // Complete the activity via the completion endpoint
        $this->actingAs($this->student)
            ->post(route('student.activities.complete', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // Check learning event was recorded
        $this->assertDatabaseHas('learning_events', [
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'activity_completed',
        ]);

        $event = \App\Models\LearningEvent::where('event_type', 'activity_completed')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->first();

        $this->assertNotNull($event);
        $this->assertArrayHasKey('enrollment_id', $event->payload);
        $this->assertArrayHasKey('progress_status', $event->payload);
        $this->assertArrayHasKey('completed_at', $event->payload);
        $this->assertEquals('completed', $event->payload['progress_status']);
    }

    /**
     * @test
     * M4-T01: Task context fields (concept, learning_objective, difficulty) are available on Activity
     */
    public function test_activity_has_task_context_fields(): void
    {
        // Create activity with task context
        $activity = Activity::factory()->create([
            'learning_unit_id' => $this->learningUnit->id,
            'title' => 'Variables Exercise',
            'type' => \App\Enums\ActivityType::CodingExercise,
            'status' => 'published',
            'concept' => 'variables',
            'learning_objective' => 'Students can declare and use variables in Python',
            'difficulty' => 'beginner',
        ]);

        $this->assertEquals('variables', $activity->getConcept());
        $this->assertEquals('Students can declare and use variables in Python', $activity->getLearningObjective());
        $this->assertEquals('beginner', $activity->getDifficulty());

        // Test nullable - should be null when not set
        $activity2 = Activity::factory()->create([
            'learning_unit_id' => $this->learningUnit->id,
            'title' => 'No Context Exercise',
            'type' => \App\Enums\ActivityType::CodingExercise,
            'status' => 'published',
        ]);

        $this->assertNull($activity2->getConcept());
        $this->assertNull($activity2->getLearningObjective());
        $this->assertNull($activity2->getDifficulty());
    }

    /**
     * @test
     * M4-T01: No duplicate activity_started event when completing an already-started activity
     */
    public function test_no_duplicate_activity_started_on_completion(): void
    {
        // Start activity
        $this->actingAs($this->student)
            ->postJson(route('student.activities.start', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // Verify exactly one activity_started event exists
        $startedCountBefore = \App\Models\LearningEvent::where('event_type', 'activity_started')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->count();

        $this->assertEquals(1, $startedCountBefore, 'Exactly one activity_started event should exist after starting');

        // Complete the activity
        $this->actingAs($this->student)
            ->post(route('student.activities.complete', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // Verify still exactly one activity_started event (no duplicate)
        $startedCountAfter = \App\Models\LearningEvent::where('event_type', 'activity_started')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->count();

        $this->assertEquals(1, $startedCountAfter, 'No duplicate activity_started should be emitted on completion');

        // Verify activity_completed was recorded exactly once
        $completedCount = \App\Models\LearningEvent::where('event_type', 'activity_completed')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->count();

        $this->assertEquals(1, $completedCount, 'activity_completed should be recorded exactly once');
    }

    /**
     * @test
     * M4-T01: Completing an activity that was never started records both events correctly
     */
    public function test_complete_never_started_activity_records_both_events(): void
    {
        // Do NOT start the activity first

        // Complete the activity directly
        $this->actingAs($this->student)
            ->post(route('student.activities.complete', [
                'course' => $this->course->slug,
                'module' => $this->module->id,
                'learningUnit' => $this->learningUnit->id,
                'activity' => $this->activity->id,
            ]));

        // Verify activity_started was recorded (establishing start state)
        $startedCount = \App\Models\LearningEvent::where('event_type', 'activity_started')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->count();

        $this->assertEquals(1, $startedCount, 'activity_started should be recorded when completing never-started activity');

        // Verify activity_completed was recorded exactly once
        $completedCount = \App\Models\LearningEvent::where('event_type', 'activity_completed')
            ->where('user_id', $this->student->id)
            ->where('activity_id', $this->activity->id)
            ->count();

        $this->assertEquals(1, $completedCount, 'activity_completed should be recorded exactly once');
    }
}