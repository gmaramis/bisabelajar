<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\LearningUnitStatus;
use App\Enums\MaterialStatus;
use App\Enums\ModuleStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCourseExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_loads(): void
    {
        $student = User::factory()->student()->create(['name' => 'Dana Student']);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Dana Student')
            ->assertSee('My Courses')
            ->assertSee('width=device-width', false);
    }

    public function test_student_login_redirects_to_dashboard(): void
    {
        $student = User::factory()->student()->create();

        $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ])->assertRedirect(route('student.dashboard'));
    }

    public function test_my_courses_displays_active_enrollments_only(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $active = Course::factory()->published()->public()->create(['title' => 'Active enrolled course']);
        $dropped = Course::factory()->published()->public()->create(['title' => 'Dropped enrolled course']);
        $foreign = Course::factory()->published()->public()->create(['title' => 'Someone else course']);

        Enrollment::factory()->for($student, 'user')->for($active)->create();
        Enrollment::factory()->for($student, 'user')->for($dropped)->dropped()->create();
        Enrollment::factory()->for($otherStudent, 'user')->for($foreign)->create();

        $this->actingAs($student)
            ->get(route('student.courses'))
            ->assertOk()
            ->assertSee('My Courses')
            ->assertSee('Active enrolled course')
            ->assertDontSee('Dropped enrolled course')
            ->assertDontSee('Someone else course');
    }

    public function test_student_can_navigate_published_course_content(): void
    {
        [$student, $course, $module, $unit, $material, $draftModule, $draftUnit, $draftMaterial] = $this->enrolledPublishedStack();

        $dashboard = $this->actingAs($student)->get(route('student.dashboard'));
        $dashboard->assertOk()->assertSee('My Courses')->assertSee($course->title);

        $courses = $this->actingAs($student)->get(route('student.courses'));
        $courses->assertOk()->assertSee($course->title);

        $coursePage = $this->actingAs($student)->get(route('student.courses.show', $course));
        $coursePage->assertOk()
            ->assertSee($module->title)
            ->assertDontSee($draftModule->title);

        $modulePage = $this->actingAs($student)->get(route('student.modules.show', [$course, $module]));
        $modulePage->assertOk()
            ->assertSee($unit->title)
            ->assertDontSee($draftUnit->title);

        $unitPage = $this->actingAs($student)->get(route('student.units.show', [$course, $module, $unit]));
        $unitPage->assertOk()
            ->assertSee($material->title)
            ->assertDontSee($draftMaterial->title);

        $this->actingAs($student)
            ->get(route('materials.show', [$course, $unit, $material]))
            ->assertOk()
            ->assertSee($material->title)
            ->assertSee('Readable content');
    }

    public function test_unenrolled_student_cannot_access_course_content(): void
    {
        [$ownerStudent, $course, $module, $unit, $material] = $this->enrolledPublishedStack();
        $stranger = User::factory()->student()->create();

        $this->actingAs($stranger)
            ->get(route('student.courses.show', $course))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('student.modules.show', [$course, $module]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('student.units.show', [$course, $module, $unit]))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->get(route('materials.show', [$course, $unit, $material]))
            ->assertForbidden();
    }

    public function test_student_cannot_access_unpublished_module_unit_or_material(): void
    {
        [$student, $course, $module, $unit, $material, $draftModule, $draftUnit, $draftMaterial] = $this->enrolledPublishedStack();

        $this->actingAs($student)
            ->get(route('student.modules.show', [$course, $draftModule]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('student.units.show', [$course, $module, $draftUnit]))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('materials.show', [$course, $unit, $draftMaterial]))
            ->assertForbidden();
    }

    public function test_student_cannot_access_tutor_editing_functions(): void
    {
        [$student, $course, $module, $unit] = $this->enrolledPublishedStack();

        $this->actingAs($student)->get(route('tutor.courses.edit', $course))->assertForbidden();
        $this->actingAs($student)->get(route('tutor.modules.edit', [$course, $module]))->assertForbidden();
        $this->actingAs($student)->get(route('tutor.units.edit', [$course, $module, $unit]))->assertForbidden();
        $this->actingAs($student)->get(route('tutor.materials.create', [$course, $module, $unit]))->assertForbidden();
    }

    public function test_tutor_cannot_access_student_dashboard(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)->get(route('student.dashboard'))->assertForbidden();
        $this->actingAs($tutor)->get(route('student.courses'))->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: LearningMaterial, 5: Module, 6: LearningUnit, 7: LearningMaterial}
     */
    private function enrolledPublishedStack(): array
    {
        $student = User::factory()->student()->create();
        $course = Course::factory()->published()->public()->create(['title' => 'Python basics']);
        $module = Module::factory()->for($course)->published()->create(['title' => 'Published module']);
        $draftModule = Module::factory()->for($course)->create(['title' => 'Hidden draft module']);
        $unit = LearningUnit::factory()->for($module)->published()->create(['title' => 'Published unit']);
        $draftUnit = LearningUnit::factory()->for($module)->create(['title' => 'Hidden draft unit']);
        $material = LearningMaterial::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Published notes',
            'content' => 'Readable content',
        ]);
        $draftMaterial = LearningMaterial::factory()->for($unit, 'learningUnit')->create([
            'title' => 'Hidden draft material',
        ]);

        Enrollment::factory()->for($student, 'user')->for($course)->create([
            'status' => EnrollmentStatus::Active,
        ]);

        return [$student, $course, $module, $unit, $material, $draftModule, $draftUnit, $draftMaterial];
    }
}
