<?php

namespace Tests\Feature;

use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_create_learning_unit(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();

        $this->actingAs($tutor)->post(route('tutor.units.store', [$course, $module]), [
            'title' => 'Variables and types',
            'description' => 'First unit',
        ])->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $unit = LearningUnit::query()->first();

        $this->assertNotNull($unit);
        $this->assertSame($module->id, $unit->module_id);
        $this->assertSame('Variables and types', $unit->title);
        $this->assertSame('variables-and-types', $unit->slug);
        $this->assertSame(LearningUnitStatus::Draft, $unit->status);
        $this->assertSame(1, $unit->sort_order);
    }

    public function test_tutor_can_create_an_arbitrary_number_of_units(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();

        $this->actingAs($tutor);
        $this->post(route('tutor.units.store', [$course, $module]), ['title' => 'One']);
        $this->post(route('tutor.units.store', [$course, $module]), ['title' => 'Two']);
        $this->post(route('tutor.units.store', [$course, $module]), ['title' => 'Three']);

        $this->assertSame(3, $module->learningUnits()->count());
        $this->assertSame([1, 2, 3], $module->learningUnits()->pluck('sort_order')->all());
    }

    public function test_tutor_can_edit_learning_unit(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        $unit = LearningUnit::factory()->for($module)->create(['title' => 'Old title']);

        $this->actingAs($tutor)->put(route('tutor.units.update', [$course, $module, $unit]), [
            'title' => 'New title',
            'description' => 'Updated',
        ])->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $unit->refresh();
        $this->assertSame('New title', $unit->title);
        $this->assertSame('new-title', $unit->slug);
    }

    public function test_tutor_can_delete_learning_unit(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        $unit = LearningUnit::factory()->for($module)->create();

        $this->actingAs($tutor)
            ->delete(route('tutor.units.destroy', [$course, $module, $unit]))
            ->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $this->assertDatabaseMissing('learning_units', ['id' => $unit->id]);
    }

    public function test_tutor_can_reorder_learning_units(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        $first = LearningUnit::factory()->for($module)->create(['title' => 'A', 'sort_order' => 0]);
        $second = LearningUnit::factory()->for($module)->create(['title' => 'B', 'sort_order' => 1]);

        $this->actingAs($tutor)->post(route('tutor.units.reorder', [$course, $module]), [
            'order' => [$second->id, $first->id],
        ])->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $this->assertSame(['B', 'A'], $module->learningUnits()->pluck('title')->all());
    }

    public function test_tutor_can_publish_and_unpublish_unit_when_module_is_published(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create();

        $this->actingAs($tutor)
            ->post(route('tutor.units.publish', [$course, $module, $unit]))
            ->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $this->assertSame(LearningUnitStatus::Published, $unit->fresh()->status);

        $this->actingAs($tutor)
            ->post(route('tutor.units.unpublish', [$course, $module, $unit]))
            ->assertRedirect(route('tutor.modules.edit', [$course, $module]));

        $this->assertSame(LearningUnitStatus::Draft, $unit->fresh()->status);
    }

    public function test_unit_cannot_be_published_when_module_is_not_published(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        $unit = LearningUnit::factory()->for($module)->create();

        $this->actingAs($tutor)
            ->post(route('tutor.units.publish', [$course, $module, $unit]))
            ->assertForbidden();

        $this->assertSame(LearningUnitStatus::Draft, $unit->fresh()->status);
    }

    public function test_tutor_cannot_manage_units_on_another_tutors_course(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($otherTutor, 'owner')->published()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['title' => 'Keep me']);

        $this->actingAs($tutor)
            ->post(route('tutor.units.store', [$course, $module]), ['title' => 'Intruder'])
            ->assertForbidden();

        $this->actingAs($tutor)
            ->put(route('tutor.units.update', [$course, $module, $unit]), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($tutor)
            ->delete(route('tutor.units.destroy', [$course, $module, $unit]))
            ->assertForbidden();

        $this->assertSame('Keep me', $unit->fresh()->title);
    }

    public function test_student_cannot_manage_learning_units(): void
    {
        $student = User::factory()->student()->create();
        [$tutor, $course, $module] = $this->ownedModule();
        $unit = LearningUnit::factory()->for($module)->create();

        $this->actingAs($student)
            ->post(route('tutor.units.store', [$course, $module]), ['title' => 'Student unit'])
            ->assertForbidden();

        $this->actingAs($student)
            ->put(route('tutor.units.update', [$course, $module, $unit]), ['title' => 'Student edit'])
            ->assertForbidden();
    }

    public function test_learning_unit_validation_requires_title(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();

        $this->actingAs($tutor)
            ->from(route('tutor.units.create', [$course, $module]))
            ->post(route('tutor.units.store', [$course, $module]), ['title' => ''])
            ->assertRedirect(route('tutor.units.create', [$course, $module]))
            ->assertSessionHasErrors('title');
    }

    public function test_slug_is_unique_per_module_and_safe(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        LearningUnit::factory()->for($module)->create([
            'title' => 'Safe Unit',
            'slug' => 'safe-unit',
        ]);

        $this->actingAs($tutor)->post(route('tutor.units.store', [$course, $module]), [
            'title' => 'Safe Unit!!!',
            'slug' => 'Safe Unit!!!',
        ]);

        $slugs = $module->learningUnits()->pluck('slug');
        $this->assertTrue($slugs->contains('safe-unit'));
        $this->assertTrue($slugs->contains('safe-unit-2'));
    }

    public function test_learning_units_have_no_meeting_number(): void
    {
        $this->assertTrue(Schema::hasTable('learning_units'));
        $this->assertFalse(Schema::hasColumn('learning_units', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('learning_units', 'lecture_number'));
        $this->assertTrue(Schema::hasColumn('learning_units', 'sort_order'));
        $this->assertTrue(Schema::hasColumn('learning_units', 'slug'));
    }

    public function test_unit_from_another_module_is_not_found(): void
    {
        [$tutor, $course, $module] = $this->ownedModule();
        $otherModule = Module::factory()->for($course)->create();
        $unit = LearningUnit::factory()->for($otherModule)->create();

        $this->actingAs($tutor)
            ->put(route('tutor.units.update', [$course, $module, $unit]), ['title' => 'Wrong module'])
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Course, 2: Module}
     */
    private function ownedModule(): array
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create();

        return [$tutor, $course, $module];
    }
}
