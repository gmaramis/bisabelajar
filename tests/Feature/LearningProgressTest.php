<?php

namespace Tests\Feature;

use App\Enums\ProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_unit_is_not_started_until_opened(): void
    {
        [$student, $course, $module, $unit, $enrollment] = $this->enrolledUnit();

        $this->actingAs($student)
            ->get(route('student.modules.show', [$course, $module]))
            ->assertOk()
            ->assertSee('NOT_STARTED');

        $this->assertSame(0, LearningProgress::query()->count());
        $this->assertSame(ProgressStatus::NotStarted, LearningProgress::statusFor(null));
        $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
        $this->assertFalse(Schema::hasColumn('learning_progress', 'mastery_score'));
        $this->assertFalse(Schema::hasTable('masteries'));
    }

    public function test_opening_unit_records_in_progress(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('IN_PROGRESS')
            ->assertSee('Completion is not mastery');

        $progress = LearningProgress::query()->first();
        $this->assertNotNull($progress);
        $this->assertSame($student->id, $progress->user_id);
        $this->assertSame($unit->id, $progress->learning_unit_id);
        $this->assertSame(ProgressStatus::InProgress, $progress->status);
        $this->assertNotNull($progress->started_at);
        $this->assertNull($progress->completed_at);
    }

    public function test_explicit_completion_records_completed_not_mastered(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();

        $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));

        $this->actingAs($student)
            ->post(route('student.progress.complete', [$course, $module, $unit]))
            ->assertRedirect(route('student.units.show', [$course, $module, $unit]));

        $progress = LearningProgress::query()->first();
        $this->assertSame(ProgressStatus::Completed, $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertNotSame('mastered', $progress->status->value);

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('COMPLETED');

        $this->assertSame(ProgressStatus::Completed, $progress->fresh()->status);
    }

    public function test_progress_persists_across_sessions(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();

        $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));
        $this->post(route('logout'));

        $this->actingAs($student)
            ->get(route('student.modules.show', [$course, $module]))
            ->assertOk()
            ->assertSee('IN_PROGRESS');

        $this->assertSame(1, LearningProgress::query()->count());
        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()->status);
    }

    public function test_student_sees_own_progress_only(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();
        $other = User::factory()->student()->create();
        Enrollment::factory()->for($other, 'user')->for($course)->create();

        $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));
        $this->actingAs($student)->post(route('student.progress.complete', [$course, $module, $unit]));

        $this->actingAs($other)
            ->get(route('student.modules.show', [$course, $module]))
            ->assertOk()
            ->assertSee('NOT_STARTED')
            ->assertDontSee('COMPLETED');

        $this->assertSame(
            1,
            LearningProgress::query()->where('user_id', $student->id)->where('status', ProgressStatus::Completed)->count(),
        );
        $this->assertSame(0, LearningProgress::query()->where('user_id', $other->id)->count());
    }

    public function test_tutor_can_see_basic_progress_for_own_course(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();
        $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));

        $this->actingAs($course->owner)
            ->get(route('tutor.courses.edit', $course))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee($unit->title)
            ->assertSee('IN_PROGRESS')
            ->assertSee('Progress is not mastery');
    }

    public function test_student_cannot_modify_another_students_progress(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledUnit();
        $intruder = User::factory()->student()->create();

        $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));

        $this->actingAs($intruder)
            ->post(route('student.progress.complete', [$course, $module, $unit]))
            ->assertForbidden();

        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()->status);
        $this->assertSame($student->id, LearningProgress::query()->first()->user_id);
        $this->assertSame(0, LearningProgress::query()->where('user_id', $intruder->id)->count());
    }

    public function test_unenrolled_student_cannot_complete_unit(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();

        $this->actingAs($student)
            ->post(route('student.progress.complete', [$course, $module, $unit]))
            ->assertForbidden();

        $this->assertSame(0, LearningProgress::query()->count());
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: Enrollment}
     */
    private function enrolledUnit(): array
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create(['title' => 'Variables unit']);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($course)->create();

        return [$student, $course, $module, $unit, $enrollment];
    }
}
