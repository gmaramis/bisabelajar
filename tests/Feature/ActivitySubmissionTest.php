<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Enums\SubmissionStatus;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivitySubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_a_started_activity(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->startedPublishedActivity();

        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $activity]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => ['body' => 'My assignment response.'],
            ])
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $submission = ActivitySubmission::query()->first();
        $this->assertNotNull($submission);
        $this->assertSame($student->id, $submission->user_id);
        $this->assertSame($activity->id, $submission->activity_id);
        $this->assertSame(1, $submission->attempt_number);
        $this->assertSame(1, $submission->version);
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
        $this->assertSame('My assignment response.', $submission->payload['body']);
        $this->assertNotNull($submission->submitted_at);
        $this->assertArrayNotHasKey('score', $submission->payload);
        $this->assertArrayNotHasKey('grade', $submission->payload);

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('My assignment response.')
            ->assertSee('Attempt 1')
            ->assertSee('SUBMITTED');
    }

    public function test_quiz_answers_persist_without_scoring(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->startedPublishedActivity([
            'type' => ActivityType::Quiz,
            'configuration' => [
                'instructions' => 'Answer the items.',
                'max_attempts' => 2,
            ],
        ]);

        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => [
                'body' => 'Attempt one.',
                'answers' => ['A', 'C'],
            ],
        ])->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $submission = ActivitySubmission::query()->first();
        $this->assertSame(['A', 'C'], $submission->payload['answers']);
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
        $this->assertArrayNotHasKey('score', $submission->payload);
    }

    public function test_multiple_attempts_are_allowed_only_when_configured(): void
    {
        [$student, $course, $module, $unit, $single] = $this->startedPublishedActivity([
            'title' => 'Single attempt lesson',
        ]);

        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $single]), [
            'payload' => ['body' => 'First and only.'],
        ])->assertRedirect();

        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $single]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $single]), [
                'payload' => ['body' => 'Second try.'],
            ])
            ->assertRedirect(route('activities.show', [$course, $unit, $single]))
            ->assertSessionHasErrors('payload');

        $this->assertSame(1, $single->submissions()->count());

        $quiz = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::Quiz)->create([
            'title' => 'Two attempt quiz',
            'configuration' => [
                'instructions' => 'Try twice.',
                'max_attempts' => 2,
            ],
        ]);
        ActivityProgress::markStarted($student->enrollments()->first(), $quiz);

        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $quiz]), [
            'payload' => ['body' => 'Quiz attempt 1'],
        ])->assertRedirect();
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $quiz]), [
            'payload' => ['body' => 'Quiz attempt 2'],
        ])->assertRedirect();
        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $quiz]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $quiz]), [
                'payload' => ['body' => 'Quiz attempt 3'],
            ])
            ->assertSessionHasErrors('payload');

        $this->assertSame([1, 2], $quiz->submissions()->pluck('attempt_number')->all());
        $this->assertSame([1, 2], $quiz->submissions()->pluck('version')->all());
    }

    public function test_invalid_payload_is_rejected(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->startedPublishedActivity();

        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $activity]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => ['body' => ''],
            ])
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]))
            ->assertSessionHasErrors('payload.body');

        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $activity]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => [
                    'body' => 'Trying to grade myself.',
                    'score' => 100,
                ],
            ])
            ->assertSessionHasErrors('payload');

        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $activity]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => [
                    'body' => 'Code on a lesson.',
                    'code' => 'print(1)',
                ],
            ])
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, ActivitySubmission::query()->count());
    }

    public function test_unauthorized_users_cannot_submit_or_see_foreign_submissions(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->startedPublishedActivity();
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Owned response.'],
        ]);

        $stranger = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();

        $this->actingAs($stranger)
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => ['body' => 'Intruder'],
            ])
            ->assertForbidden();
        $this->actingAs($tutor)
            ->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
                'payload' => ['body' => 'Tutor submit'],
            ])
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertForbidden();

        $ownerTutor = $course->owner;
        $this->actingAs($ownerTutor)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertDontSee('Owned response.');
        $this->actingAs($otherTutor)
            ->get(route('tutor.activities.edit', [$course, $module, $unit, $activity]))
            ->assertForbidden();

        $this->assertSame(1, ActivitySubmission::query()->count());
        $this->assertSame($student->id, ActivitySubmission::query()->first()->user_id);
    }

    public function test_draft_archived_and_unstarted_activities_cannot_be_submitted(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->startedPublishedActivity();
        $draft = Activity::factory()->for($unit, 'learningUnit')->create(['status' => ActivityStatus::Draft]);
        $archived = Activity::factory()->for($unit, 'learningUnit')->archived()->create();
        $unstarted = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Not started yet',
        ]);

        $this->actingAs($student)
            ->post(route('student.activities.submit', [$course, $module, $unit, $draft]), [
                'payload' => ['body' => 'Draft'],
            ])
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.activities.submit', [$course, $module, $unit, $archived]), [
                'payload' => ['body' => 'Archived'],
            ])
            ->assertForbidden();
        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $unstarted]))
            ->post(route('student.activities.submit', [$course, $module, $unit, $unstarted]), [
                'payload' => ['body' => 'Too early'],
            ])
            ->assertRedirect(route('activities.show', [$course, $unit, $unstarted]))
            ->assertSessionHasErrors('payload');

        $this->assertSame(0, ActivitySubmission::query()->count());
        $this->assertTrue($activity->isPublished());
    }

    /**
     * @param  array<string, mixed>  $activityAttributes
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: Activity}
     */
    private function startedPublishedActivity(array $activityAttributes = []): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->create(array_merge([
            'title' => 'Submittable activity',
        ], $activityAttributes));
        $enrollment = Enrollment::factory()->for($student, 'user')->for($course)->create();
        ActivityProgress::markStarted($enrollment, $activity);

        return [$student, $course, $module, $unit, $activity];
    }
}
