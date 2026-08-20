<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_create_course(): void
    {
        $tutor = User::factory()->tutor()->create();

        $response = $this->actingAs($tutor)->post(route('tutor.courses.store'), [
            'title' => 'Intro to Python',
            'description' => 'A configurable programming course.',
            'visibility' => CourseVisibility::Private->value,
        ]);

        $course = Course::query()->first();

        $this->assertNotNull($course);
        $this->assertSame($tutor->id, $course->owner_id);
        $this->assertSame('Intro to Python', $course->title);
        $this->assertSame('intro-to-python', $course->slug);
        $this->assertSame(CourseStatus::Draft, $course->status);
        $response->assertRedirect(route('tutor.courses.edit', $course));
    }

    public function test_tutor_can_edit_own_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($tutor)->put(route('tutor.courses.update', $course), [
            'title' => 'Updated title',
            'description' => 'Updated description',
            'visibility' => CourseVisibility::Unlisted->value,
            'thumbnail' => 'https://example.com/thumb.png',
        ])->assertRedirect(route('tutor.courses.edit', $course));

        $course->refresh();

        $this->assertSame('Updated title', $course->title);
        $this->assertSame('updated-title', $course->slug);
        $this->assertSame(CourseVisibility::Unlisted, $course->visibility);
    }

    public function test_tutor_can_publish_own_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($tutor)
            ->post(route('tutor.courses.publish', $course))
            ->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame(CourseStatus::Published, $course->fresh()->status);
    }

    public function test_tutor_can_archive_own_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->create();

        $this->actingAs($tutor)
            ->post(route('tutor.courses.archive', $course))
            ->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame(CourseStatus::Archived, $course->fresh()->status);
    }

    public function test_tutor_cannot_modify_another_tutors_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($otherTutor, 'owner')->create([
            'title' => 'Original title',
        ]);

        $this->actingAs($tutor)
            ->get(route('tutor.courses.edit', $course))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->put(route('tutor.courses.update', $course), [
                'title' => 'Hijacked title',
                'visibility' => CourseVisibility::Public->value,
            ])
            ->assertForbidden();

        $this->actingAs($tutor)
            ->post(route('tutor.courses.publish', $course))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->post(route('tutor.courses.archive', $course))
            ->assertForbidden();

        $this->assertSame('Original title', $course->fresh()->title);
        $this->assertSame(CourseStatus::Draft, $course->fresh()->status);
    }

    public function test_student_cannot_edit_course(): void
    {
        $student = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($student)
            ->get(route('tutor.courses.create'))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('tutor.courses.store'), [
                'title' => 'Student course',
                'visibility' => CourseVisibility::Public->value,
            ])
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('tutor.courses.edit', $course))
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('tutor.courses.update', $course), [
                'title' => 'Student edit',
                'visibility' => CourseVisibility::Public->value,
            ])
            ->assertForbidden();
    }

    public function test_slug_is_unique_and_safe(): void
    {
        $tutor = User::factory()->tutor()->create();
        Course::factory()->for($tutor, 'owner')->create([
            'title' => 'Safe Course',
            'slug' => 'safe-course',
        ]);

        $this->actingAs($tutor)->post(route('tutor.courses.store'), [
            'title' => 'Safe Course!!!',
            'slug' => 'Safe Course!!!',
            'visibility' => CourseVisibility::Private->value,
        ]);

        $slugs = Course::query()->pluck('slug');

        $this->assertTrue($slugs->contains('safe-course'));
        $this->assertTrue($slugs->contains('safe-course-2'));
        $this->assertFalse($slugs->contains('Safe Course!!!'));
    }

    public function test_course_validation_requires_title_and_visibility(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->from(route('tutor.courses.create'))
            ->post(route('tutor.courses.store'), [
                'title' => '',
                'visibility' => 'invalid',
            ])
            ->assertRedirect(route('tutor.courses.create'))
            ->assertSessionHasErrors(['title', 'visibility']);

        $this->assertSame(0, Course::query()->count());
    }

    public function test_courses_have_no_semester_or_meeting_number(): void
    {
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertFalse(Schema::hasColumn('courses', 'semester'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_count'));
    }

    public function test_unauthenticated_users_cannot_manage_courses(): void
    {
        $course = Course::factory()->create();

        $this->post(route('tutor.courses.store'), [
            'title' => 'Guest course',
            'visibility' => CourseVisibility::Public->value,
        ])->assertRedirect(route('login'));

        $this->put(route('tutor.courses.update', $course), [
            'title' => 'Guest edit',
            'visibility' => CourseVisibility::Public->value,
        ])->assertRedirect(route('login'));
    }
}
