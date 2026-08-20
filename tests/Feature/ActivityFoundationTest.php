<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use ValueError;

class ActivityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_belongs_to_a_learning_unit(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create([
            'title' => 'Warm-up lesson',
        ]);

        $this->assertTrue($activity->learningUnit->is($unit));
        $this->assertTrue($unit->activities->contains($activity));
        $this->assertSame('Warm-up lesson', $unit->activities()->first()->title);
    }

    public function test_supported_activity_types_persist(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->assertCount(7, ActivityType::cases());

        foreach (ActivityType::cases() as $index => $type) {
            $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Activity '.$type->value,
                'type' => $type->value,
            ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

            $activity = $unit->activities()->where('sort_order', $index + 1)->first();
            $this->assertNotNull($activity);
            $this->assertSame($type, $activity->type);
            $this->assertSame(ActivityStatus::Draft, $activity->status);
            $this->assertSame([], $activity->configuration);
        }

        $this->assertSame(7, $unit->activities()->count());
    }

    public function test_invalid_activity_type_is_rejected(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Unknown',
                'type' => 'flashcard',
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('type');

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_activity_status_is_constrained(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $draft = Activity::factory()->for($unit, 'learningUnit')->create();
        $published = Activity::factory()->for($unit, 'learningUnit')->published()->create();
        $archived = Activity::factory()->for($unit, 'learningUnit')->archived()->create();

        $this->assertSame(ActivityStatus::Draft, $draft->status);
        $this->assertSame(ActivityStatus::Published, $published->status);
        $this->assertSame(ActivityStatus::Archived, $archived->status);

        $this->expectException(ValueError::class);
        Activity::factory()->for($unit, 'learningUnit')->create([
            'status' => 'mastered',
        ]);
    }

    public function test_activity_configuration_is_an_extensible_json_boundary(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create([
            'configuration' => ['prompt' => 'Explain variables', 'max_attempts' => 3],
        ]);

        $fresh = $activity->fresh();
        $this->assertSame('Explain variables', $fresh->configuration['prompt']);
        $this->assertSame(3, $fresh->configuration['max_attempts']);
    }

    public function test_activity_ordering_persists(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $first = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'A', 'sort_order' => 0]);
        $second = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'B', 'sort_order' => 1]);

        $this->actingAs($tutor)->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$second->id, $first->id],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame(['B', 'A'], $unit->activities()->pluck('title')->all());
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_tutor_cannot_manage_another_tutors_activities(): void
    {
        [$owner, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create();
        $otherTutor = User::factory()->tutor()->create();

        $this->actingAs($otherTutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Intruder',
            'type' => ActivityType::Lesson->value,
        ])->assertForbidden();

        $this->actingAs($otherTutor)->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$activity->id],
        ])->assertForbidden();

        $this->assertFalse(Gate::forUser($otherTutor)->allows('create', [Activity::class, $unit]));
        $this->assertFalse(Gate::forUser($otherTutor)->allows('update', $activity));
        $this->assertFalse(Gate::forUser($otherTutor)->allows('reorder', [Activity::class, $unit]));
    }

    public function test_student_cannot_create_or_reorder_activities(): void
    {
        $student = User::factory()->student()->create();
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create();

        $this->actingAs($student)->get(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertForbidden();

        $this->actingAs($student)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Student activity',
            'type' => ActivityType::Quiz->value,
        ])->assertForbidden();

        $this->actingAs($student)->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$activity->id],
        ])->assertForbidden();

        $this->assertFalse(Gate::forUser($student)->allows('create', [Activity::class, $unit]));
        $this->assertFalse(Gate::forUser($student)->allows('update', $activity));
    }

    public function test_owning_tutor_is_authorized_to_manage_activities(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create();

        $this->actingAs($tutor)
            ->get(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Add activity');

        $this->assertTrue(Gate::forUser($tutor)->allows('create', [Activity::class, $unit]));
        $this->assertTrue(Gate::forUser($tutor)->allows('update', $activity));
        $this->assertTrue(Gate::forUser($tutor)->allows('reorder', [Activity::class, $unit]));
        $this->assertTrue(Gate::forUser($tutor)->allows('view', $activity));
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit}
     */
    private function ownedUnit(): array
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create();
        $module = Module::factory()->for($course)->create();
        $unit = LearningUnit::factory()->for($module)->create();

        return [$tutor, $course, $module, $unit];
    }
}
