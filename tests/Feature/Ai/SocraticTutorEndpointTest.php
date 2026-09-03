<?php

namespace Tests\Feature\Ai;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\EnrollmentStatus;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocraticTutorEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Course $course;
    private Module $module;
    private LearningUnit $unit;
    private Activity $activity;
    private string $hintUrl;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.socratic'                  => 'gemini',
            'ai.providers.gemini.keys'     => ['test-gemini-key'],
            'ai.providers.gemini.model'    => 'gemini-3.8-flash',
            'ai.providers.openrouter.key'  => 'test-or-key',
        ]);

        $tutor        = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course  = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $this->module  = Module::factory()->for($this->course)->published()->create();
        $this->unit    = LearningUnit::factory()->for($this->module)->published()->create();
        $this->activity = Activity::factory()
            ->for($this->unit, 'learningUnit')
            ->published()
            ->type(ActivityType::CodingExercise)
            ->create([
                'concept'            => 'recursion',
                'learning_objective' => 'Write recursive functions.',
                'bloom_demand'       => BloomLevel::Apply,
            ]);

        // Enroll the student actively
        Enrollment::factory()->create([
            'user_id'   => $this->student->id,
            'course_id' => $this->course->id,
            'status'    => EnrollmentStatus::Active,
        ]);

        $this->hintUrl = route('student.activities.ai-hint', [
            $this->course, $this->module, $this->unit, $this->activity,
        ]);
    }

    private function geminiSuccessResponse(): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => 'What does the base case do in your recursive function?']]],
            ]],
        ];
    }

    public function test_enrolled_student_gets_200_with_hint(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiSuccessResponse(), 200)]);

        $this->actingAs($this->student)
            ->postJson($this->hintUrl, [
                'error_message'   => 'RecursionError: max depth',
                'test_case_label' => 'Test 1',
                'attempt_count'   => 2,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['hint', 'response_type', 'advisory_only', 'provider', 'model'])
            ->assertJsonFragment(['advisory_only' => true]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->postJson($this->hintUrl, ['attempt_count' => 1])
            ->assertStatus(401);
    }

    public function test_tutor_role_gets_403(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->postJson($this->hintUrl, ['attempt_count' => 1])
            ->assertStatus(403);
    }

    public function test_not_enrolled_student_gets_403(): void
    {
        $otherStudent = User::factory()->student()->create();

        $this->actingAs($otherStudent)
            ->postJson($this->hintUrl, ['attempt_count' => 1])
            ->assertStatus(403);
    }

    public function test_invalid_activity_gets_404(): void
    {
        $badUrl = route('student.activities.ai-hint', [
            $this->course, $this->module, $this->unit, 99999,
        ]);

        $this->actingAs($this->student)
            ->postJson($badUrl, ['attempt_count' => 1])
            ->assertStatus(404);
    }

    public function test_missing_attempt_count_gets_422(): void
    {
        $this->actingAs($this->student)
            ->postJson($this->hintUrl, ['error_message' => 'some error'])
            // no attempt_count
            ->assertStatus(422)
            ->assertJsonValidationErrors(['attempt_count']);
    }

    public function test_all_providers_down_returns_503_with_friendly_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 429),
            'openrouter.ai/*'    => Http::response([], 500),
        ]);

        $this->actingAs($this->student)
            ->postJson($this->hintUrl, ['attempt_count' => 3])
            ->assertStatus(503)
            ->assertJsonStructure(['hint', 'advisory_only'])
            ->assertJsonFragment(['advisory_only' => true]);
    }

    public function test_response_does_not_expose_pii(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiSuccessResponse(), 200)]);

        $response = $this->actingAs($this->student)
            ->postJson($this->hintUrl, [
                'error_message' => 'Error on line 5',
                'attempt_count' => 1,
            ])
            ->assertStatus(200)
            ->json();

        $serialized = json_encode($response);
        $this->assertStringNotContainsString($this->student->email, $serialized);
        $this->assertStringNotContainsString((string) $this->student->id, $serialized);
    }
}
