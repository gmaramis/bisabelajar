<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tutor_can_select_type_and_save_valid_configuration(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->get(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Type')
            ->assertSee('LESSON')
            ->assertSee('QUIZ')
            ->assertSee('CODING_EXERCISE')
            ->assertSee('Student instructions');

        $payloads = [
            ActivityType::Lesson->value => [
                'instructions' => 'Read the lesson notes.',
                'tutor' => ['notes' => 'Remind students about variables.'],
            ],
            ActivityType::Quiz->value => [
                'instructions' => 'Answer each item.',
                'max_attempts' => 3,
                'time_limit_minutes' => 15,
                'tutor' => ['notes' => 'Private quiz note', 'answer_key' => 'SECRET-QUIZ-KEY'],
            ],
            ActivityType::Assignment->value => [
                'instructions' => 'Submit a short report.',
                'tutor' => ['notes' => 'Look for structure.', 'rubric' => 'PRIVATE-RUBRIC'],
            ],
            ActivityType::CodingExercise->value => [
                'instructions' => 'Write a function that returns 42.',
                'language' => 'python',
                'tutor' => ['expected_output' => 'PRIVATE-OUTPUT'],
            ],
            ActivityType::Discussion->value => [
                'instructions' => 'Reply to classmates.',
                'prompt' => 'Which concept was hardest?',
                'tutor' => ['notes' => 'Keep the thread focused.'],
            ],
            ActivityType::Project->value => [
                'instructions' => 'Build a small CLI tool.',
                'tutor' => ['rubric' => 'PRIVATE-PROJECT-RUBRIC'],
            ],
            ActivityType::Exam->value => [
                'instructions' => 'Complete the exam independently.',
                'max_attempts' => 1,
                'time_limit_minutes' => 60,
                'tutor' => ['answer_key' => 'SECRET-EXAM-KEY'],
            ],
        ];

        foreach ($payloads as $type => $configuration) {
            $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Configured '.$type,
                'type' => $type,
                'configuration' => $configuration,
            ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

            $activity = $unit->activities()->where('title', 'Configured '.$type)->first();
            $this->assertNotNull($activity);
            $this->assertSame($configuration['instructions'], $activity->configuration['instructions']);
            $this->assertSame($configuration, $activity->configuration);
        }
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Broken quiz',
                'type' => ActivityType::Quiz->value,
                'configuration' => [
                    'instructions' => 'Try this quiz.',
                    'max_attempts' => 0,
                ],
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('configuration.max_attempts');

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Lesson with answer key',
                'type' => ActivityType::Lesson->value,
                'configuration' => [
                    'instructions' => 'Read this.',
                    'tutor' => ['answer_key' => 'should-not-save'],
                ],
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('configuration.tutor');

        $this->actingAs($tutor)
            ->from(route('tutor.activities.create', [$course, $module, $unit]))
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Discussion without prompt',
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
                'title' => 'Scoring leak',
                'type' => ActivityType::Quiz->value,
                'configuration' => [
                    'instructions' => 'Do not score this.',
                    'scoring' => ['auto' => true],
                ],
            ])
            ->assertRedirect(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('configuration');

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_extensible_private_extensions_persist(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)->post(route('tutor.activities.store', [$course, $module, $unit]), [
            'title' => 'Extensible lesson',
            'type' => ActivityType::Lesson->value,
            'configuration' => [
                'instructions' => 'Read the notes.',
                'extensions' => ['future_flag' => 'pilot'],
            ],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $activity = Activity::query()->first();
        $this->assertSame('pilot', $activity->configuration['extensions']['future_flag']);
        $this->assertArrayNotHasKey('extensions', $activity->studentSafeConfiguration());
        $this->assertArrayNotHasKey('tutor', $activity->studentSafeConfiguration());
    }

    public function test_tutor_private_configuration_is_not_exposed_to_students(): void
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::Quiz)->create([
            'title' => 'Unit quiz',
            'configuration' => [
                'instructions' => 'Answer carefully.',
                'max_attempts' => 2,
                'tutor' => [
                    'notes' => 'PRIVATE-TUTOR-NOTES',
                    'answer_key' => 'SECRET-ANSWER-KEY',
                ],
                'extensions' => ['internal_tag' => 'PRIVATE-EXTENSION'],
            ],
        ]);

        $safe = $activity->studentSafeConfiguration();
        $this->assertSame([
            'instructions' => 'Answer carefully.',
            'max_attempts' => 2,
        ], $safe);
        $this->assertArrayNotHasKey('tutor', $safe);
        $this->assertArrayNotHasKey('answer_key', $safe);
        $this->assertArrayNotHasKey('extensions', $safe);
        $this->assertSame('SECRET-ANSWER-KEY', $activity->tutorPrivateConfiguration()['tutor']['answer_key']);

        $student = User::factory()->student()->create();
        Enrollment::factory()->for($student, 'user')->for($course)->create();

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertDontSee('SECRET-ANSWER-KEY')
            ->assertDontSee('PRIVATE-TUTOR-NOTES')
            ->assertDontSee('PRIVATE-EXTENSION');

        $this->actingAs($student)
            ->get(route('tutor.units.edit', [$course, $module, $unit]))
            ->assertForbidden();
        $this->actingAs($student)
            ->get(route('tutor.activities.create', [$course, $module, $unit]))
            ->assertForbidden();

        $this->actingAs($tutor)
            ->get(route('tutor.units.edit', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Answer carefully.')
            ->assertSee('Includes tutor-private configuration')
            ->assertDontSee('SECRET-ANSWER-KEY');
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
