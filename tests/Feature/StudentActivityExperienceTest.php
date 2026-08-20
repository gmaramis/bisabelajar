<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
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

class StudentActivityExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_published_activities_in_order(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedUnit();
        $second = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Second published activity',
            'sort_order' => 1,
        ]);
        $first = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'First published activity',
            'sort_order' => 0,
        ]);
        $draft = Activity::factory()->for($unit, 'learningUnit')->create([
            'title' => 'Hidden draft activity',
            'status' => ActivityStatus::Draft,
            'sort_order' => 2,
        ]);
        $archived = Activity::factory()->for($unit, 'learningUnit')->archived()->create([
            'title' => 'Hidden archived activity',
            'sort_order' => 3,
        ]);

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSeeInOrder(['First published activity', 'Second published activity'])
            ->assertSee('LESSON')
            ->assertSee('NOT_STARTED')
            ->assertDontSee('Hidden draft activity')
            ->assertDontSee('Hidden archived activity');

        $this->assertTrue($first->isPublished());
        $this->assertTrue($second->isPublished());
        $this->assertSame(0, ActivityProgress::query()->count());
    }

    public function test_student_can_open_published_activity_and_see_student_safe_details(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::Quiz)->create([
            'title' => 'Visible quiz',
            'configuration' => [
                'instructions' => 'Answer carefully.',
                'max_attempts' => 2,
                'time_limit_minutes' => 15,
                'tutor' => ['answer_key' => 'SECRET-STUDENT-KEY'],
            ],
        ]);

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Visible quiz');

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('Visible quiz')
            ->assertSee('QUIZ')
            ->assertSee('Answer carefully.')
            ->assertSee('Max attempts: 2')
            ->assertSee('Time limit: 15 minutes')
            ->assertSee('Start activity')
            ->assertDontSee('SECRET-STUDENT-KEY');

        $this->assertSame(0, ActivityProgress::query()->count());
    }

    public function test_student_start_state_persists_and_is_not_mastery(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Startable lesson',
        ]);

        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $progress = ActivityProgress::query()->first();
        $this->assertNotNull($progress);
        $this->assertSame($student->id, $progress->user_id);
        $this->assertSame($activity->id, $progress->activity_id);
        $this->assertSame(ProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNull($progress->completed_at);
        $this->assertNotSame('mastered', $progress->status->value);

        $startedAt = $progress->started_at;

        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('activities.show', [$course, $unit, $activity]));

        $this->assertSame(1, ActivityProgress::query()->count());
        $this->assertTrue($startedAt->equalTo($progress->fresh()->started_at));

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('IN_PROGRESS')
            ->assertDontSee('Start activity');

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('IN_PROGRESS');

        $this->assertFalse(Schema::hasColumn('activity_progress', 'mastery_score'));
        $this->assertFalse(Schema::hasTable('masteries'));
        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()?->status);
    }

    public function test_draft_and_archived_activities_cannot_be_started_or_opened(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedUnit();
        $draft = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'Draft only']);
        $archived = Activity::factory()->for($unit, 'learningUnit')->archived()->create(['title' => 'Archived only']);

        $this->actingAs($student)->get(route('activities.show', [$course, $unit, $draft]))->assertForbidden();
        $this->actingAs($student)->get(route('activities.show', [$course, $unit, $archived]))->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $draft]))
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $archived]))
            ->assertForbidden();

        $this->assertSame(0, ActivityProgress::query()->count());
    }

    public function test_enrollment_is_required_to_view_or_start_activities(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Enrolled only',
        ]);
        $stranger = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($stranger)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertForbidden();
        $this->actingAs($stranger)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertForbidden();
        $this->actingAs($stranger)
            ->post(route('student.activities.start', [$course, $module, $unit, $activity]))
            ->assertForbidden();
        $this->actingAs($tutor)
            ->post(route('student.activities.start', [$course, $module, $unit, $activity]))
            ->assertForbidden();

        $this->assertSame(0, ActivityProgress::query()->count());
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit}
     */
    private function enrolledPublishedUnit(): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        return [$student, $course, $module, $unit];
    }
}
