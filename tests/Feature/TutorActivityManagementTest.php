<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CompletionRule;
use App\Enums\CourseStatus;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorActivityManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_manages_activities_end_to_end(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedPublishedUnit();

        $this->actingAs($tutor)
            ->get(route('tutor.units.edit', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Activities')
            ->assertSee('Add activity')
            ->assertSee('No activities yet.');

        $this->actingAs($tutor)
            ->get(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Add activity')
            ->assertSee('Type')
            ->assertSee('LESSON')
            ->assertSee('QUIZ')
            ->assertSee('ASSIGNMENT')
            ->assertSee('CODING_EXERCISE')
            ->assertSee('DISCUSSION')
            ->assertSee('PROJECT')
            ->assertSee('EXAM')
            ->assertSee('Student instructions')
            ->assertSee('Completion rule')
            ->assertSee('data-activity-fields="discussion"', false)
            ->assertSee('data-activity-fields="quiz exam"', false)
            ->assertSee('data-activity-fields="coding_exercise"', false)
            ->assertSee('data-activity-fields="assignment project"', false);

        $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Warm-up lesson',
            'type' => ActivityType::Lesson->value,
            'configuration' => [
                'instructions' => 'Read the notes.',
                'completion_rule' => CompletionRule::Manual->value,
                'tutor' => ['notes' => 'Keep it short.'],
            ],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Unit quiz',
            'type' => ActivityType::Quiz->value,
            'configuration' => [
                'instructions' => 'Answer each item.',
                'max_attempts' => 2,
                'time_limit_minutes' => 10,
                'tutor' => ['answer_key' => 'PRIVATE-QUIZ-KEY'],
            ],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $lesson = $unit->activities()->where('title', 'Warm-up lesson')->first();
        $quiz = $unit->activities()->where('title', 'Unit quiz')->first();
        $this->assertNotNull($lesson);
        $this->assertNotNull($quiz);
        $this->assertSame(ActivityStatus::Draft, $lesson->status);
        $this->assertSame(['PRIVATE-QUIZ-KEY'], [$quiz->configuration['tutor']['answer_key']]);

        $this->actingAs($tutor)
            ->get(route('tutor.units.edit', [$course, $module, $unit]))
            ->assertOk()
            ->assertSeeInOrder(['Warm-up lesson', 'Unit quiz'])
            ->assertSee('LESSON')
            ->assertSee('QUIZ')
            ->assertSee('Edit')
            ->assertSee('Publish')
            ->assertSee('Unpublish')
            ->assertSee('Archive')
            ->assertSee('Up')
            ->assertDontSee('PRIVATE-QUIZ-KEY');

        $this->actingAs($tutor)->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$quiz->id, $lesson->id],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->assertSame(['Unit quiz', 'Warm-up lesson'], $unit->activities()->pluck('title')->all());

        $this->actingAs($tutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $quiz]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->assertSame(ActivityStatus::Published, $quiz->fresh()->status);

        $this->actingAs($tutor)
            ->get(route('tutor.activities.edit', [$course, $module, $unit, $quiz]))
            ->assertOk()
            ->assertSee('Edit activity')
            ->assertSee('Unit quiz')
            ->assertSee('Answer each item.')
            ->assertSee('PRIVATE-QUIZ-KEY')
            ->assertSee('Publish')
            ->assertSee('Unpublish')
            ->assertSee('Archive')
            ->assertSee('Back to activities');

        $this->actingAs($tutor)
            ->put(route('tutor.activities.update', [$course, $module, $unit, $quiz]), [
                'title' => 'Revised unit quiz',
                'type' => ActivityType::Quiz->value,
                'configuration' => [
                    'instructions' => 'Answer carefully.',
                    'max_attempts' => 3,
                    'tutor' => ['answer_key' => 'PRIVATE-QUIZ-KEY'],
                ],
            ])
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->actingAs($tutor)
            ->post(route('tutor.activities.unpublish', [$course, $module, $unit, $quiz]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->actingAs($tutor)
            ->post(route('tutor.activities.archive', [$course, $module, $unit, $quiz]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame('Revised unit quiz', $quiz->fresh()->title);
        $this->assertSame(3, $quiz->fresh()->configuration['max_attempts']);
        $this->assertSame(ActivityStatus::Archived, $quiz->fresh()->status);
        $this->assertSame(ActivityStatus::Draft, $lesson->fresh()->status);
    }

    public function test_type_selection_and_configuration_validation_work(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Broken discussion',
                'type' => ActivityType::Discussion->value,
                'configuration' => [
                    'instructions' => 'Talk about it.',
                ],
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('configuration.prompt');

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Lesson with quiz fields',
                'type' => ActivityType::Lesson->value,
                'configuration' => [
                    'instructions' => 'Read this.',
                    'max_attempts' => 3,
                ],
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('configuration');

        $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Coding warmup',
            'type' => ActivityType::CodingExercise->value,
            'configuration' => [
                'instructions' => 'Return 42.',
                'language' => 'python',
                'tutor' => ['expected_output' => '42'],
            ],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $activity = Activity::query()->first();
        $this->assertSame(ActivityType::CodingExercise, $activity->type);
        $this->assertSame('python', $activity->configuration['language']);
        $this->assertSame('42', $activity->configuration['tutor']['expected_output']);
        $this->assertArrayNotHasKey('max_attempts', $activity->configuration);
    }

    public function test_ownership_is_enforced_for_tutor_activity_management(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedPublishedUnit();
        $activity = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'Owned activity']);
        $otherTutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();

        foreach ([$otherTutor, $student] as $actor) {
            $this->actingAs($actor)
                ->get(route('tutor.units.edit', [$course, $module, $unit]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->get(route('tutor.activities.create', [$course, $module, $unit]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->get(route('tutor.activities.edit', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                    'title' => 'Intruder',
                    'type' => ActivityType::Lesson->value,
                    'configuration' => ['instructions' => 'No'],
                ])
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
                ->assertForbidden();
            $this->actingAs($actor)
                ->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
                    'order' => [$activity->id],
                ])
                ->assertForbidden();
        }

        $this->assertSame(1, Activity::query()->count());
        $this->assertSame('Owned activity', $activity->fresh()->title);
        $this->assertTrue($course->isOwnedBy($tutor));
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
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit}
     */
    private function ownedPublishedUnit(): array
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->create(['status' => CourseStatus::Published]);
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);

        return [$tutor, $course, $module, $unit];
    }
}
