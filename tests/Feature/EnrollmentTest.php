<?php

namespace Tests\Feature;

use App\Enums\CourseVisibility;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_enroll_in_accessible_published_course(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create([
            'title' => 'Intro to Python',
        ]);

        $this->actingAs($student)
            ->post(route('enrollments.store', $course), [
                'user_id' => User::factory()->student()->create()->id,
            ])
            ->assertRedirect(route('student.courses'));

        $enrollment = Enrollment::query()->first();

        $this->assertNotNull($enrollment);
        $this->assertSame($student->id, $enrollment->user_id);
        $this->assertSame($course->id, $enrollment->course_id);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_duplicate_enrollment_is_prevented(): void
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create();
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        $this->actingAs($student)
            ->from(route('courses.show', $course))
            ->post(route('enrollments.store', $course))
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHasErrors('course');

        $this->assertSame(1, Enrollment::query()->count());
    }

    public function test_student_sees_own_enrollments_only(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $ownCourse = Course::factory()->published()->public()->create(['title' => 'Owned enrollment course']);
        $otherCourse = Course::factory()->published()->public()->create(['title' => 'Hidden other enrollment']);

        Enrollment::factory()->for($student, 'user')->for($ownCourse)->create();
        Enrollment::factory()->for($otherStudent, 'user')->for($otherCourse)->create();

        $this->actingAs($student)
            ->get(route('student.courses'))
            ->assertOk()
            ->assertSee('Owned enrollment course')
            ->assertDontSee('Hidden other enrollment');
    }

    public function test_tutor_sees_enrollments_for_own_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $otherCourse = Course::factory()->for($otherTutor, 'owner')->published()->public()->create();
        $student = User::factory()->student()->create(['name' => 'Enrolled Student Name']);
        $otherStudent = User::factory()->student()->create(['name' => 'Other Course Student']);

        Enrollment::factory()->for($student, 'user')->for($course)->create();
        Enrollment::factory()->for($otherStudent, 'user')->for($otherCourse)->create();

        $this->actingAs($tutor)
            ->get(route('tutor.courses.edit', $course))
            ->assertOk()
            ->assertSee('Enrolled Student Name')
            ->assertDontSee('Other Course Student');
    }

    public function test_tutor_cannot_see_enrollments_for_another_tutors_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($otherTutor, 'owner')->published()->public()->create();
        $student = User::factory()->student()->create(['name' => 'Secret Enrollee']);
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        $this->actingAs($tutor)
            ->get(route('tutor.courses.edit', $course))
            ->assertForbidden();
    }

    public function test_student_cannot_enroll_another_student(): void
    {
        $student = User::factory()->student()->create();
        $target = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create();

        $this->actingAs($student)->post(route('enrollments.store', $course), [
            'user_id' => $target->id,
        ]);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $target->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_student_cannot_enroll_in_unpublished_or_private_course(): void
    {
        $student = User::factory()->student()->create();
        $draft = Course::factory()->public()->create();
        $privatePublished = Course::factory()->published()->create([
            'visibility' => CourseVisibility::Private,
        ]);

        $this->actingAs($student)
            ->post(route('enrollments.store', $draft))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('enrollments.store', $privatePublished))
            ->assertForbidden();

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_tutor_cannot_create_enrollment(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->published()->public()->create();

        $this->actingAs($tutor)
            ->post(route('enrollments.store', $course))
            ->assertForbidden();

        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_student_cannot_view_another_tutors_enrollment_list(): void
    {
        $student = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();

        $this->actingAs($student)
            ->get(route('tutor.courses.edit', $course))
            ->assertForbidden();
    }

    public function test_enrollment_statuses_are_supported(): void
    {
        $active = Enrollment::factory()->active()->create();
        $completed = Enrollment::factory()->completed()->create();
        $dropped = Enrollment::factory()->dropped()->create();

        $this->assertSame(EnrollmentStatus::Active, $active->status);
        $this->assertSame(EnrollmentStatus::Completed, $completed->status);
        $this->assertSame(EnrollmentStatus::Dropped, $dropped->status);
    }
}
