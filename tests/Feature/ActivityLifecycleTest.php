<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CourseStatus;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ActivityLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_edit_owned_activity(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create([
            'title' => 'Draft lesson',
            'type' => ActivityType::Lesson,
        ]);

        $this->actingAs($tutor)
            ->get(route('tutor.activities.edit', [$course, $module, $unit, $activity]))
            ->assertOk()
            ->assertSee('Edit activity')
            ->assertSee('Draft lesson');

        $this->actingAs($tutor)
            ->put(route('tutor.activities.update', [$course, $module, $unit, $activity]), [
                'title' => 'Revised lesson',
                'type' => ActivityType::Lesson->value,
                'configuration' => [
                    'instructions' => 'Updated student instructions.',
                    'tutor' => ['notes' => 'Private revision note'],
                ],
            ])
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $activity->refresh();
        $this->assertSame('Revised lesson', $activity->title);
        $this->assertSame(ActivityStatus::Draft, $activity->status);
        $this->assertSame('Updated student instructions.', $activity->configuration['instructions']);
        $this->assertSame('Private revision note', $activity->configuration['tutor']['notes']);
    }

    public function test_activity_ordering_persists_across_lifecycle_actions(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $first = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'A', 'sort_order' => 0]);
        $second = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'B', 'sort_order' => 1]);
        $third = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'C', 'sort_order' => 2]);

        $this->actingAs($tutor)->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$third->id, $first->id, $second->id],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame(['C', 'A', 'B'], $unit->activities()->pluck('title')->all());
    }

    public function test_activity_publish_requires_published_parents(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create();

        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertForbidden();
        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);

        $course->update(['status' => CourseStatus::Published]);
        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertForbidden();

        $module->update(['status' => ModuleStatus::Published]);
        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertForbidden();

        $unit->update(['status' => LearningUnitStatus::Published]);
        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame(ActivityStatus::Published, $activity->fresh()->status);
    }

    public function test_tutor_can_unpublish_and_archive_owned_activity(): void
    {
        [$tutor, $course, $module, $unit, $activity] = $this->publishedActivityStack();

        $this->actingAs($tutor)
            ->post(route('tutor.activities.unpublish', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->assertSame(ActivityStatus::Draft, $activity->fresh()->status);

        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->actingAs($tutor)
            ->post(route('tutor.activities.archive', [$course, $module, $unit, $activity]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->assertSame(ActivityStatus::Archived, $activity->fresh()->status);

        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
            ->assertForbidden();
        $this->actingAs($tutor)
            ->post(route('tutor.activities.unpublish', [$course, $module, $unit, $activity]))
            ->assertForbidden();
        $this->actingAs($tutor)
            ->post(route('tutor.activities.archive', [$course, $module, $unit, $activity]))
            ->assertForbidden();
    }

    public function test_students_cannot_access_draft_or_archived_activities(): void
    {
        [$tutor, $course, $module, $unit, $activity] = $this->publishedActivityStack();
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        $activity->update(['status' => ActivityStatus::Draft]);
        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertForbidden();
        $this->assertFalse(Gate::forUser($student)->allows('view', $activity->fresh()));

        $activity->update(['status' => ActivityStatus::Archived]);
        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertForbidden();
        $this->assertFalse(Gate::forUser($student)->allows('view', $activity->fresh()));
        $this->assertFalse(Gate::forUser($student)->allows('update', $activity->fresh()));
    }

    public function test_enrolled_student_can_view_published_activity_without_private_configuration(): void
    {
        [$tutor, $course, $module, $unit, $activity] = $this->publishedActivityStack([
            'title' => 'Visible quiz',
            'type' => ActivityType::Quiz,
            'configuration' => [
                'instructions' => 'Answer carefully.',
                'max_attempts' => 2,
                'tutor' => ['answer_key' => 'SECRET-LIFECYCLE-KEY'],
            ],
        ]);
        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        $this->actingAs($student)
            ->get(route('activities.show', [$course, $unit, $activity]))
            ->assertOk()
            ->assertSee('Visible quiz')
            ->assertSee('Answer carefully.')
            ->assertDontSee('SECRET-LIFECYCLE-KEY');

        $this->assertTrue(Gate::forUser($student)->allows('view', $activity));
    }

    public function test_student_and_other_tutor_cannot_manage_activity_lifecycle(): void
    {
        [$tutor, $course, $module, $unit, $activity] = $this->publishedActivityStack();
        $student = User::factory()->student()->create();
        $otherTutor = User::factory()->tutor()->create();

        foreach ([$student, $otherTutor] as $actor) {
            $this->actingAs($actor)
                ->get(route('tutor.activities.edit', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->put(route('tutor.activities.update', [$course, $module, $unit, $activity]), [
                    'title' => 'Hijacked',
                    'type' => ActivityType::Lesson->value,
                    'configuration' => ['instructions' => 'Nope'],
                ])
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.unpublish', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.archive', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
                    'order' => [$activity->id],
                ])
                ->assertForbidden();
        }

        $this->assertSame('Published activity', $activity->fresh()->title);
        $this->assertSame(ActivityStatus::Published, $activity->fresh()->status);
    }

    public function test_activity_from_another_unit_is_not_found(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $otherUnit = LearningUnit::factory()->for($module)->create();
        $activity = Activity::factory()->for($otherUnit, 'learningUnit')->create();

        $this->actingAs($tutor)
            ->get(route('tutor.activities.edit', [$course, $module, $unit, $activity]))
            ->assertNotFound();
        $this->actingAs($tutor)
            ->post(route('tutor.activities.archive', [$course, $module, $unit, $activity]))
            ->assertNotFound();
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

    /**
     * @param  array<string, mixed>  $activityAttributes
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: Activity}
     */
    private function publishedActivityStack(array $activityAttributes = []): array
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->create(array_merge([
            'title' => 'Published activity',
        ], $activityAttributes));

        return [$tutor, $course, $module, $unit, $activity];
    }
}
