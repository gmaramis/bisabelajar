<?php

namespace Tests\Feature;

use App\Enums\ModuleStatus;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_create_module(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($tutor)->post(route('tutor.modules.store', $course), [
            'title' => 'Getting started',
            'description' => 'First module',
        ])->assertRedirect(route('tutor.courses.edit', $course));

        $module = Module::query()->first();

        $this->assertNotNull($module);
        $this->assertSame($course->id, $module->course_id);
        $this->assertSame('Getting started', $module->title);
        $this->assertSame(ModuleStatus::Draft, $module->status);
        $this->assertSame(1, $module->sort_order);
    }

    public function test_tutor_can_create_an_arbitrary_number_of_modules(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($tutor);
        $this->post(route('tutor.modules.store', $course), ['title' => 'One']);
        $this->post(route('tutor.modules.store', $course), ['title' => 'Two']);
        $this->post(route('tutor.modules.store', $course), ['title' => 'Three']);

        $this->assertSame(3, $course->modules()->count());
        $this->assertSame([1, 2, 3], $course->modules()->pluck('sort_order')->all());
    }

    public function test_tutor_can_edit_module(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create(['title' => 'Old title']);

        $this->actingAs($tutor)->put(route('tutor.modules.update', [$course, $module]), [
            'title' => 'New title',
            'description' => 'Updated',
        ])->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame('New title', $module->fresh()->title);
    }

    public function test_tutor_can_delete_module(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create();

        $this->actingAs($tutor)
            ->delete(route('tutor.modules.destroy', [$course, $module]))
            ->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
    }

    public function test_tutor_can_reorder_modules(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $first = Module::factory()->for($course)->create(['title' => 'A', 'sort_order' => 0]);
        $second = Module::factory()->for($course)->create(['title' => 'B', 'sort_order' => 1]);

        $this->actingAs($tutor)->post(route('tutor.modules.reorder', $course), [
            'order' => [$second->id, $first->id],
        ])->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(['B', 'A'], $course->modules()->pluck('title')->all());
    }

    public function test_tutor_can_publish_and_unpublish_module_when_course_is_published(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->create();
        $module = Module::factory()->for($course)->create();

        $this->actingAs($tutor)
            ->post(route('tutor.modules.publish', [$course, $module]))
            ->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame(ModuleStatus::Published, $module->fresh()->status);

        $this->actingAs($tutor)
            ->post(route('tutor.modules.unpublish', [$course, $module]))
            ->assertRedirect(route('tutor.courses.edit', $course));

        $this->assertSame(ModuleStatus::Draft, $module->fresh()->status);
    }

    public function test_module_cannot_be_published_when_course_is_not_published(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create();

        $this->actingAs($tutor)
            ->post(route('tutor.modules.publish', [$course, $module]))
            ->assertForbidden();

        $this->assertSame(ModuleStatus::Draft, $module->fresh()->status);
    }

    public function test_tutor_cannot_manage_modules_on_another_tutors_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($otherTutor, 'owner')->published()->create();
        $module = Module::factory()->for($course)->create(['title' => 'Keep me']);

        $this->actingAs($tutor)
            ->post(route('tutor.modules.store', $course), ['title' => 'Intruder'])
            ->assertForbidden();

        $this->actingAs($tutor)
            ->put(route('tutor.modules.update', [$course, $module]), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('tutor.modules.destroy', [$course, $module]))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->post(route('tutor.modules.reorder', $course), ['order' => [$module->id]])
            ->assertForbidden();

        $this->assertSame('Keep me', $module->fresh()->title);
        $this->assertSame(1, $course->modules()->count());
    }

    public function test_student_cannot_manage_modules(): void
    {
        $student = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create();

        $this->actingAs($student)
            ->post(route('tutor.modules.store', $course), ['title' => 'Student module'])
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('tutor.modules.update', [$course, $module]), ['title' => 'Student edit'])
            ->assertForbidden();
    }

    public function test_module_validation_requires_title(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();

        $this->actingAs($tutor)
            ->from(route('tutor.modules.create', $course))
            ->post(route('tutor.modules.store', $course), ['title' => ''])
            ->assertRedirect(route('tutor.modules.create', $course))
            ->assertSessionHasErrors('title');
    }

    public function test_modules_have_no_meeting_number(): void
    {
        $this->assertTrue(Schema::hasTable('modules'));
        $this->assertFalse(Schema::hasColumn('modules', 'meeting_number'));
        $this->assertTrue(Schema::hasColumn('modules', 'sort_order'));
    }

    public function test_module_from_another_course_is_not_found(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $otherCourse = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($otherCourse)->create();

        $this->actingAs($tutor)
            ->put(route('tutor.modules.update', [$course, $module]), ['title' => 'Wrong course'])
            ->assertNotFound();
    }
}
