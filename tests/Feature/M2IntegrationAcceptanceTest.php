<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Enums\CompletionRule;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningUnitStatus;
use App\Enums\ModuleStatus;
use App\Enums\ProgressStatus;
use App\Enums\SubmissionStatus;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Composer\InstalledVersions;
use Filament\FilamentServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class M2IntegrationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_m2_tutor_and_student_end_to_end_acceptance_flow(): void
    {
        $tutor = User::factory()->tutor()->create([
            'email' => 'tutor-m2@example.com',
            'password' => 'password',
        ]);
        $student = User::factory()->student()->create([
            'email' => 'student-m2@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $tutor->email,
            'password' => 'password',
        ])->assertRedirect(route('tutor.workspace'));

        $this->post(route('tutor.courses.store'), [
            'title' => 'Activity Engine Pilot',
            'description' => 'Generic configurable activities.',
            'visibility' => CourseVisibility::Public->value,
        ])->assertRedirect();

        $course = Course::query()->first();
        $this->assertNotNull($course);
        $this->assertSame($tutor->id, $course->owner_id);

        $this->post(route('tutor.modules.store', $course), ['title' => 'Practice']);
        $module = $course->modules()->first();
        $this->post(route('tutor.units.store', [$course, $module]), ['title' => 'First activities']);
        $unit = $module->learningUnits()->first();
        $this->assertNotNull($unit);

        $this->actingAs($tutor)
            ->get(route('tutor.units.edit', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Activities')
            ->assertSee('Add activity');

        foreach ($this->activityBlueprints() as $blueprint) {
            $this->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => $blueprint['title'],
                'type' => $blueprint['type']->value,
                'configuration' => $blueprint['configuration'],
            ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        }

        $this->assertSame(7, $unit->activities()->count());
        $this->assertSame(
            ActivityType::cases(),
            $unit->activities()->orderBy('sort_order')->get()->pluck('type')->all(),
        );

        $quiz = $unit->activities()->where('title', 'Unit quiz')->first();
        $lesson = $unit->activities()->where('title', 'Warm-up lesson')->first();
        $assignment = $unit->activities()->where('title', 'Short assignment')->first();
        $coding = $unit->activities()->where('title', 'Coding warmup')->first();
        $discussion = $unit->activities()->where('title', 'Class discussion')->first();
        $project = $unit->activities()->where('title', 'Draft project')->first();
        $exam = $unit->activities()->where('title', 'Archived exam')->first();

        $this->assertSame('SECRET-M2-QUIZ-KEY', $quiz->configuration['tutor']['answer_key']);
        $this->assertSame(2, $quiz->configuration['max_attempts']);
        $this->assertSame(CompletionRule::Manual, $lesson->completionRule());

        $this->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
            'order' => [$quiz->id, $lesson->id, $assignment->id, $coding->id, $discussion->id, $project->id, $exam->id],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->assertSame(
            ['Unit quiz', 'Warm-up lesson', 'Short assignment', 'Coding warmup', 'Class discussion', 'Draft project', 'Archived exam'],
            $unit->activities()->pluck('title')->all(),
        );

        $this->post(route('tutor.courses.publish', $course));
        $this->post(route('tutor.modules.publish', [$course, $module]));
        $this->post(route('tutor.units.publish', [$course, $module, $unit]));

        foreach ([$quiz, $lesson, $assignment, $coding, $discussion, $exam] as $activity) {
            $this->post(route('tutor.activities.publish', [$course, $module, $unit, $activity]))
                ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        }

        $this->post(route('tutor.activities.unpublish', [$course, $module, $unit, $assignment]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));
        $this->post(route('tutor.activities.archive', [$course, $module, $unit, $exam]))
            ->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame(ActivityStatus::Published, $quiz->fresh()->status);
        $this->assertSame(ActivityStatus::Published, $lesson->fresh()->status);
        $this->assertSame(ActivityStatus::Draft, $assignment->fresh()->status);
        $this->assertSame(ActivityStatus::Draft, $project->fresh()->status);
        $this->assertSame(ActivityStatus::Archived, $exam->fresh()->status);
        $this->assertSame(ModuleStatus::Published, $module->fresh()->status);
        $this->assertSame(LearningUnitStatus::Published, $unit->fresh()->status);

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->post(route('enrollments.store', $course))
            ->assertRedirect(route('student.courses'));
        $this->assertSame(EnrollmentStatus::Active, Enrollment::query()->first()->status);

        $this->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSeeInOrder(['Unit quiz', 'Warm-up lesson', 'Coding warmup', 'Class discussion'])
            ->assertSee('NOT_STARTED')
            ->assertDontSee('Short assignment')
            ->assertDontSee('Draft project')
            ->assertDontSee('Archived exam')
            ->assertDontSee('SECRET-M2-QUIZ-KEY');

        $this->get(route('activities.show', [$course, $unit, $quiz]))
            ->assertOk()
            ->assertSee('Unit quiz')
            ->assertSee('QUIZ')
            ->assertSee('Answer each item.')
            ->assertSee('Start activity')
            ->assertDontSee('SECRET-M2-QUIZ-KEY');

        $this->post(route('student.activities.start', [$course, $module, $unit, $quiz]))
            ->assertRedirect(route('activities.show', [$course, $unit, $quiz]));
        $this->assertSame(ProgressStatus::InProgress, ActivityProgress::query()->where('activity_id', $quiz->id)->first()->status);

        $this->post(route('student.activities.submit', [$course, $module, $unit, $quiz]), [
            'payload' => [
                'body' => 'My quiz answers.',
                'answers' => ['A', 'C'],
            ],
        ])->assertRedirect(route('activities.show', [$course, $unit, $quiz]));

        $submission = ActivitySubmission::query()->first();
        $this->assertSame($student->id, $submission->user_id);
        $this->assertSame(1, $submission->attempt_number);
        $this->assertSame(SubmissionStatus::Submitted, $submission->status);
        $this->assertSame(['A', 'C'], $submission->payload['answers']);
        $this->assertArrayNotHasKey('score', $submission->payload);

        $this->post(route('student.activities.complete', [$course, $module, $unit, $quiz]))
            ->assertRedirect(route('activities.show', [$course, $unit, $quiz]));
        $this->assertSame(ProgressStatus::Completed, ActivityProgress::query()->where('activity_id', $quiz->id)->first()->status);

        $this->post(route('student.activities.start', [$course, $module, $unit, $lesson]));
        $this->post(route('student.activities.complete', [$course, $module, $unit, $lesson]))
            ->assertRedirect(route('activities.show', [$course, $unit, $lesson]));

        $this->post(route('student.activities.start', [$course, $module, $unit, $coding]));
        $this->post(route('student.activities.submit', [$course, $module, $unit, $coding]), [
            'payload' => [
                'body' => 'Function written.',
                'code' => 'def answer(): return 42',
            ],
        ])->assertRedirect(route('activities.show', [$course, $unit, $coding]));

        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()->status);
        $this->assertNotSame(
            LearningProgress::query()->first()->status,
            ActivityProgress::query()->where('activity_id', $quiz->id)->first()->status,
        );

        $this->post(route('logout'));
        $this->assertGuest();
        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('COMPLETED')
            ->assertSee('IN_PROGRESS');
        $this->get(route('activities.show', [$course, $unit, $quiz]))
            ->assertOk()
            ->assertSee('COMPLETED')
            ->assertSee('My quiz answers.');

        $this->assertSame(ProgressStatus::Completed, ActivityProgress::query()->where('activity_id', $quiz->id)->first()->status);
        $this->assertSame(ProgressStatus::Completed, ActivityProgress::query()->where('activity_id', $lesson->id)->first()->status);
        $this->assertSame(ProgressStatus::InProgress, ActivityProgress::query()->where('activity_id', $coding->id)->first()->status);
        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()->status);
        $this->assertSame(1, ActivityProgress::query()->where('status', ProgressStatus::Completed)->where('activity_id', $quiz->id)->count());
    }

    public function test_m2_security_and_regression_guards(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();

        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create(['title' => 'Owned activities']);
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $published = Activity::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Published quiz',
            'type' => ActivityType::Quiz,
            'configuration' => [
                'instructions' => 'Answer carefully.',
                'max_attempts' => 2,
                'tutor' => ['answer_key' => 'SECRET-M2-GUARD'],
            ],
        ]);
        $draft = Activity::factory()->for($unit, 'learningUnit')->create(['title' => 'Hidden draft activity']);
        $archived = Activity::factory()->for($unit, 'learningUnit')->archived()->create(['title' => 'Hidden archived activity']);

        $this->actingAs($otherTutor)
            ->get(route('tutor.activities.edit', [$course, $module, $unit, $published]))
            ->assertForbidden();
        $this->actingAs($otherTutor)
            ->post(route('tutor.activities.store', [$course, $module, $unit]), [
                'title' => 'Intruder activity',
                'type' => ActivityType::Lesson->value,
                'configuration' => ['instructions' => 'No'],
            ])
            ->assertForbidden();
        $this->actingAs($otherTutor)
            ->post(route('tutor.activities.publish', [$course, $module, $unit, $published]))
            ->assertForbidden();
        $this->actingAs($otherTutor)
            ->post(route('tutor.activities.reorder', [$course, $module, $unit]), [
                'order' => [$published->id],
            ])
            ->assertForbidden();

        $this->actingAs($student)->get(route('tutor.units.edit', [$course, $module, $unit]))->assertForbidden();
        $this->actingAs($student)->get(route('tutor.courses.edit', $course))->assertForbidden();
        $this->actingAs($student)->post(route('enrollments.store', $course));

        $this->actingAs($student)->get(route('activities.show', [$course, $unit, $draft]))->assertForbidden();
        $this->actingAs($student)->get(route('activities.show', [$course, $unit, $archived]))->assertForbidden();
        $this->actingAs($student)
            ->post(route('student.activities.start', [$course, $module, $unit, $draft]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertOk()
            ->assertSee('Published quiz')
            ->assertDontSee('Hidden draft activity')
            ->assertDontSee('Hidden archived activity')
            ->assertDontSee('SECRET-M2-GUARD');

        $this->actingAs($student)->post(route('student.activities.start', [$course, $module, $unit, $published]));
        $this->actingAs($student)->post(route('student.activities.submit', [$course, $module, $unit, $published]), [
            'payload' => ['body' => 'Owned submission.'],
        ]);

        $this->actingAs($intruder)
            ->post(route('student.activities.submit', [$course, $module, $unit, $published]), [
                'payload' => ['body' => 'Stolen submission.'],
            ])
            ->assertForbidden();
        $this->actingAs($intruder)
            ->post(route('student.activities.complete', [$course, $module, $unit, $published]))
            ->assertForbidden();

        $this->assertSame(1, ActivitySubmission::query()->count());
        $this->assertSame($student->id, ActivitySubmission::query()->first()->user_id);
        $this->assertSame(0, ActivityProgress::query()->where('user_id', $intruder->id)->count());
        $this->assertSame(1, Activity::query()->where('title', 'Published quiz')->count());

        $this->assertFalse(class_exists(FilamentServiceProvider::class));
        $this->assertFalse(class_exists('App\\Nexus\\NexusService'));
        $this->assertFalse(Schema::hasTable('masteries'));
        // M3 tables now exist
        $this->assertTrue(Schema::hasTable('code_executions'));
        $this->assertTrue(Schema::hasTable('language_execution_profiles'));
        $this->assertTrue(Schema::hasTable('programming_activities'));
        $this->assertTrue(Schema::hasTable('test_cases'));
        $this->assertTrue(Schema::hasTable('test_results'));
        $this->assertTrue(Schema::hasTable('learning_events'));
        $this->assertFalse(Schema::hasColumn('activity_progress', 'mastery_score'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_count'));
        $this->assertFalse(Schema::hasColumn('modules', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('learning_units', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('activities', 'meeting_number'));
        $this->assertSame('v13.26.1', InstalledVersions::getPrettyVersion('laravel/framework'));
    }

    /**
     * @return list<array{title: string, type: ActivityType, configuration: array<string, mixed>}>
     */
    private function activityBlueprints(): array
    {
        return [
            [
                'title' => 'Warm-up lesson',
                'type' => ActivityType::Lesson,
                'configuration' => [
                    'instructions' => 'Read the notes.',
                    'completion_rule' => CompletionRule::Manual->value,
                    'tutor' => ['notes' => 'Keep it short.'],
                ],
            ],
            [
                'title' => 'Unit quiz',
                'type' => ActivityType::Quiz,
                'configuration' => [
                    'instructions' => 'Answer each item.',
                    'max_attempts' => 2,
                    'time_limit_minutes' => 15,
                    'tutor' => ['answer_key' => 'SECRET-M2-QUIZ-KEY'],
                ],
            ],
            [
                'title' => 'Short assignment',
                'type' => ActivityType::Assignment,
                'configuration' => [
                    'instructions' => 'Write a short report.',
                    'tutor' => ['rubric' => 'PRIVATE-RUBRIC'],
                ],
            ],
            [
                'title' => 'Coding warmup',
                'type' => ActivityType::CodingExercise,
                'configuration' => [
                    'instructions' => 'Return 42.',
                    'language' => 'python',
                    'tutor' => ['expected_output' => '42'],
                ],
            ],
            [
                'title' => 'Class discussion',
                'type' => ActivityType::Discussion,
                'configuration' => [
                    'instructions' => 'Reply to classmates.',
                    'prompt' => 'Which concept was hardest?',
                ],
            ],
            [
                'title' => 'Draft project',
                'type' => ActivityType::Project,
                'configuration' => [
                    'instructions' => 'Build a small tool.',
                    'tutor' => ['rubric' => 'PRIVATE-PROJECT'],
                ],
            ],
            [
                'title' => 'Archived exam',
                'type' => ActivityType::Exam,
                'configuration' => [
                    'instructions' => 'Complete independently.',
                    'max_attempts' => 1,
                    'tutor' => ['answer_key' => 'SECRET-M2-EXAM'],
                ],
            ],
        ];
    }
}
