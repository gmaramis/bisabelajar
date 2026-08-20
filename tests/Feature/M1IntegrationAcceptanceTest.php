<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Enums\EnrollmentStatus;
use App\Enums\LearningUnitStatus;
use App\Enums\MaterialType;
use App\Enums\ModuleStatus;
use App\Enums\ProgressStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class M1IntegrationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_m1_tutor_and_student_end_to_end_acceptance_flow(): void
    {
        $tutor = User::factory()->tutor()->create([
            'email' => 'tutor@example.com',
            'password' => 'password',
        ]);
        $student = User::factory()->student()->create([
            'email' => 'student@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $tutor->email,
            'password' => 'password',
        ])->assertRedirect(route('tutor.workspace'));

        $this->post(route('tutor.courses.store'), [
            'title' => 'Intro to Python',
            'description' => 'Configurable programming course.',
            'visibility' => CourseVisibility::Public->value,
        ])->assertRedirect();

        $course = Course::query()->first();
        $this->assertNotNull($course);
        $this->assertSame($tutor->id, $course->owner_id);
        $this->assertSame(CourseStatus::Draft, $course->status);

        $this->post(route('tutor.modules.store', $course), ['title' => 'Syntax']);
        $this->post(route('tutor.modules.store', $course), ['title' => 'Control flow']);
        $syntax = $course->modules()->where('title', 'Syntax')->first();
        $control = $course->modules()->where('title', 'Control flow')->first();
        $this->assertNotNull($syntax);
        $this->assertNotNull($control);

        $this->post(route('tutor.modules.reorder', $course), [
            'order' => [$control->id, $syntax->id],
        ])->assertRedirect(route('tutor.courses.edit', $course));
        $this->assertSame(['Control flow', 'Syntax'], $course->modules()->pluck('title')->all());

        $this->post(route('tutor.units.store', [$course, $syntax]), ['title' => 'Hello world']);
        $this->post(route('tutor.units.store', [$course, $syntax]), ['title' => 'Variables']);
        $hello = $syntax->learningUnits()->where('title', 'Hello world')->first();
        $variables = $syntax->learningUnits()->where('title', 'Variables')->first();

        $this->post(route('tutor.units.reorder', [$course, $syntax]), [
            'order' => [$variables->id, $hello->id],
        ])->assertRedirect(route('tutor.modules.edit', [$course, $syntax]));
        $this->assertSame(['Variables', 'Hello world'], $syntax->learningUnits()->pluck('title')->all());

        $this->post(route('tutor.materials.store', [$course, $syntax, $hello]), [
            'title' => 'Intro notes',
            'type' => MaterialType::RichText->value,
            'content' => 'print("hello")',
        ]);
        $this->post(route('tutor.materials.store', [$course, $syntax, $hello]), [
            'title' => 'Lesson PDF',
            'type' => MaterialType::Pdf->value,
            'file' => UploadedFile::fake()->create('lesson.pdf', 80, 'application/pdf'),
        ]);
        $this->post(route('tutor.materials.store', [$course, $syntax, $hello]), [
            'title' => 'Lesson slides',
            'type' => MaterialType::Powerpoint->value,
            'file' => UploadedFile::fake()->create(
                'slides.pptx',
                120,
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ),
        ]);
        $this->post(route('tutor.materials.store', [$course, $syntax, $hello]), [
            'title' => 'Python docs',
            'type' => MaterialType::ExternalUrl->value,
            'external_url' => 'https://example.com/python',
        ]);

        $this->assertSame(4, $hello->materials()->count());

        $this->post(route('tutor.courses.publish', $course));
        $this->post(route('tutor.modules.publish', [$course, $syntax]));
        $this->post(route('tutor.units.publish', [$course, $syntax, $hello]));
        foreach ($hello->materials()->get() as $material) {
            $this->post(route('tutor.materials.publish', [$course, $syntax, $hello, $material]))
                ->assertRedirect();
        }

        $course->refresh();
        $syntax->refresh();
        $hello->refresh();
        $this->assertSame(CourseStatus::Published, $course->status);
        $this->assertSame(ModuleStatus::Published, $syntax->status);
        $this->assertSame(LearningUnitStatus::Published, $hello->status);
        $this->assertSame(4, $hello->materials()->where('status', 'published')->count());

        $this->post(route('logout'));

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Intro to Python');
        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee('Intro to Python');

        $this->post(route('enrollments.store', $course))
            ->assertRedirect(route('student.courses'));
        $this->assertSame(1, Enrollment::query()->where('user_id', $student->id)->where('course_id', $course->id)->count());
        $this->assertSame(EnrollmentStatus::Active, Enrollment::query()->first()->status);

        $this->from(route('courses.show', $course))
            ->post(route('enrollments.store', $course))
            ->assertRedirect(route('courses.show', $course))
            ->assertSessionHasErrors('course');
        $this->assertSame(1, Enrollment::query()->count());

        $this->get(route('student.courses'))
            ->assertOk()
            ->assertSee('Intro to Python');

        $this->get(route('student.courses.show', $course))
            ->assertOk()
            ->assertSee('Syntax')
            ->assertDontSee('Control flow');

        $this->get(route('student.modules.show', [$course, $syntax]))
            ->assertOk()
            ->assertSee('Hello world')
            ->assertSee('NOT_STARTED');

        $this->get(route('student.units.show', [$course, $syntax, $hello]))
            ->assertOk()
            ->assertSee('IN_PROGRESS')
            ->assertSee('Intro notes')
            ->assertSee('Lesson PDF')
            ->assertSee('Lesson slides')
            ->assertSee('Python docs');

        $this->assertSame(ProgressStatus::InProgress, LearningProgress::query()->first()->status);

        $richText = $hello->materials()->where('type', MaterialType::RichText)->first();
        $pdf = $hello->materials()->where('type', MaterialType::Pdf)->first();
        $slides = $hello->materials()->where('type', MaterialType::Powerpoint)->first();
        $url = $hello->materials()->where('type', MaterialType::ExternalUrl)->first();

        $this->get(route('materials.show', [$course, $hello, $richText]))
            ->assertOk()
            ->assertSee('print("hello")');
        $this->get(route('materials.show', [$course, $hello, $pdf]))
            ->assertOk()
            ->assertSee('Open PDF');
        $this->get(route('materials.download', [$course, $hello, $pdf]))->assertOk();
        $this->get(route('materials.show', [$course, $hello, $slides]))
            ->assertOk()
            ->assertSee('Download PowerPoint');
        $this->get(route('materials.download', [$course, $hello, $slides]))->assertOk();
        $this->get(route('materials.show', [$course, $hello, $url]))
            ->assertOk()
            ->assertSee('https://example.com/python');

        $this->post(route('student.progress.complete', [$course, $syntax, $hello]))
            ->assertRedirect(route('student.units.show', [$course, $syntax, $hello]));
        $this->assertSame(ProgressStatus::Completed, LearningProgress::query()->first()->status);
        $this->assertNotSame('mastered', LearningProgress::query()->first()->status->value);

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->get(route('student.modules.show', [$course, $syntax]))
            ->assertOk()
            ->assertSee('COMPLETED');
        $this->assertSame(1, LearningProgress::query()->count());
        $this->assertSame(ProgressStatus::Completed, LearningProgress::query()->first()->status);
    }

    public function test_m1_security_and_regression_guards(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create(['title' => 'Public Python']);
        $private = Course::factory()->for($tutor, 'owner')->published()->create([
            'title' => 'Secret private course',
            'visibility' => CourseVisibility::Private,
        ]);
        $draft = Course::factory()->for($tutor, 'owner')->public()->create(['title' => 'Unpublished draft course']);
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Public Python')
            ->assertDontSee('Secret private course')
            ->assertDontSee('Unpublished draft course');

        $this->actingAs($student)->get(route('courses.show', $private))->assertForbidden();
        $this->actingAs($student)->get(route('tutor.courses.edit', $course))->assertForbidden();
        $this->actingAs($student)->post(route('tutor.modules.store', $course), ['title' => 'Nope'])->assertForbidden();
        $this->actingAs($otherTutor)->get(route('tutor.courses.edit', $course))->assertForbidden();

        $this->post(route('logout'));
        $this->post(route('tutor.courses.store'), [
            'title' => 'Guest course',
            'visibility' => CourseVisibility::Public->value,
        ])->assertRedirect(route('login'));

        $this->actingAs($student)->post(route('enrollments.store', $course));
        $intruder = User::factory()->student()->create();
        $this->actingAs($intruder)
            ->post(route('student.progress.complete', [$course, $module, $unit]))
            ->assertForbidden();
        $this->assertSame(0, LearningProgress::query()->where('user_id', $intruder->id)->count());

        $this->assertFalse(class_exists(\Filament\FilamentServiceProvider::class));
        $this->assertFalse(Schema::hasColumn('courses', 'semester'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('courses', 'meeting_count'));
        $this->assertFalse(Schema::hasColumn('modules', 'meeting_number'));
        $this->assertFalse(Schema::hasColumn('learning_units', 'meeting_number'));
        $this->assertFalse(class_exists('App\\Nexus\\NexusService'));
        $this->assertFalse(Schema::hasTable('masteries'));
        $this->assertSame('v13.26.1', \Composer\InstalledVersions::getPrettyVersion('laravel/framework'));
    }
}
