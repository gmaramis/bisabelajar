<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CompletionRule;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Enums\ProgressStatus;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ActivityProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_activity_is_not_started(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('NOT_STARTED');

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('NOT_STARTED');

        $this->assertSame(0, ActivityProgress::query()->count());
        $this->assertSame(ProgressStatus::NotStarted, ActivityProgress::statusFor(null));
        $this->assertFalse(Schema::hasColumn('activity_progress', 'mastery_score'));
        $this->assertFalse(Schema::hasTable('masteries'));
    }

    public function test_start_records_in_progress(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();

        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $progress = ActivityProgress::query()->first();
        $this->assertNotNull($progress);
        $this->assertSame($student->id, $progress->user_id);
        $this->assertSame($activity->id, $progress->activity_id);
        $this->assertSame($student->enrollments()->first()->id, $progress->enrollment_id);
        $this->assertSame(ProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNull($progress->completed_at);

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('IN_PROGRESS');
    }

    public function test_valid_submission_completion_records_completed_not_mastered(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Ready to complete.'],
        ]);

        $this->actingAs($student)
            ->post(route('student.activities.complete', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $progress = ActivityProgress::query()->first();
        $this->assertSame(ProgressStatus::Completed, $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertNotSame('mastered', $progress->status->value);

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('COMPLETED')
            ->assertDontSee('Mark activity complete');

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('COMPLETED');

        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()?->status);
        $this->assertSame(ProgressStatus::Completed, $progress->fresh()->status);
    }

    public function test_completion_rule_is_configurable(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)
            ->from(route('activities.show', [$course, $unit, $activity]))
            ->post(route('student.activities.complete', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]))
            ->assertSessionHasErrors('completion');

        $this->assertSame(ProgressStatus::InProgress, ActivityProgress::query()->first()->status);

        $manual = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Manual completion lesson',
            'configuration' => [
                'instructions' => 'Mark complete when finished.',
                'completion_rule' => CompletionRule::Manual->value,
            ],
        ]);

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $manual]));
        $this->actingAs($student)
            ->post(route('student.activities.complete', [$course, $module, $unit, $manual]))
            ->assertRedirect(route('activities.show', [$course, $unit, $manual]));

        $this->assertSame(
            ProgressStatus::Completed,
            ActivityProgress::query()->where('activity_id', $manual->id)->first()->status,
        );
        $this->assertSame(CompletionRule::Manual, $manual->completionRule());
        $this->assertSame(CompletionRule::Submission, $activity->completionRule());
    }

    public function test_activity_progress_persists_and_stays_separate_from_unit_progress(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Done.'],
        ]);
        $this->actingAs($student)->post(route('student.activities.complete', [$course, $module, $unit, $activity]));
        $this->post(route('logout'));

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('COMPLETED');

        $this->assertSame(1, ActivityProgress::query()->count());
        $this->assertSame(ProgressStatus::Completed, ActivityProgress::query()->first()->status);
        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()?->status);
        $this->assertNotSame(
            LearningProgress::query()->first()?->status,
            ActivityProgress::query()->first()->status,
        );
    }

    public function test_student_sees_own_activity_progress_only(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();
        $other = User::factory()->student()->create();
        Enrollment::factory()->for($other, 'user')->for($course)->create();

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Mine.'],
        ]);
        $this->actingAs($student)->post(route('student.activities.complete', [$course, $module, $unit, $activity]));

        $this->actingAs($other)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('NOT_STARTED')
            ->assertDontSee('COMPLETED');

        $this->assertSame(
            1,
            ActivityProgress::query()->where('user_id', $student->id)->where('status', ProgressStatus::Completed)->count(),
        );
        $this->assertSame(0, ActivityProgress::query()->where('user_id', $other->id)->count());
    }

    public function test_tutor_can_see_course_activity_progress(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity([
            'title' => 'Visible activity progress',
        ]);

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Tutor can review participation.'],
        ]);
        $this->actingAs($student)->post(route('student.activities.complete', [$course, $module, $unit, $activity]));

        $this->actingAs($course->owner)
            ->get(route('tutor.courses.edit', $course))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Visible activity progress')
            ->assertSee('COMPLETED')
            ->assertSee('Activity progress is not mastery');

        $otherTutor = User::factory()->tutor()->create();
        $this->actingAs($otherTutor)
            ->get(route('tutor.courses.edit', $course))
            ->assertForbidden();
    }

    public function test_unauthorized_users_cannot_complete_activity(): void
    {
        [$student, $course, $module, $unit, $activity] = $this->publishedActivity();
        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $activity]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $activity]), [
            'payload' => ['body' => 'Owned work.'],
        ]);

        $intruder = User::factory()->student()->create();
        $draft = Activity::factory()->for($unit, 'learningUnit')->create(['status' => ActivityStatus::Draft]);

        $this->actingAs($intruder)
            ->post(route('student.activities.complete', [$course, $module, $unit, $activity]))
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.activities.complete', [$course, $module, $unit, $draft]))
            ->assertForbidden();

        $this->assertSame(ProgressStatus::InProgress, ActivityProgress::query()->first()->status);
        $this->assertSame($student->id, ActivityProgress::query()->first()->user_id);
        $this->assertSame(0, ActivityProgress::query()->where('user_id', $intruder->id)->count());
        $this->assertSame(0, LearningProgress::query()->where('status', ProgressStatus::Completed)->count());
    }

    /**
     * @param  array<string, mixed>  $activityAttributes
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: Activity}
     */
    private function publishedActivity(array $activityAttributes = []): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->create(array_merge([
            'title' => 'Progress activity',
            'type' => ActivityType::Lesson,
        ], $activityAttributes));
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        return [$student, $course, $module, $unit, $activity];
    }
}
