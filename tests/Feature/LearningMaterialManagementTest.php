<?php

namespace Tests\Feature;

use App\Enums\LearningUnitStatus;
use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Enums\ModuleStatus;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningMaterialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_tutor_can_add_rich_text_material(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Intro notes',
            'type' => MaterialType::RichText->value,
            'content' => 'Welcome to the unit.',
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $material = LearningMaterial::query()->first();
        $this->assertNotNull($material);
        $this->assertSame(MaterialType::RichText, $material->type);
        $this->assertSame('Welcome to the unit.', $material->content);
        $this->assertSame(MaterialStatus::Draft, $material->status);
        $this->assertNull($material->file_path);
    }

    public function test_tutor_can_upload_pdf_material(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $file = UploadedFile::fake()->create('lesson notes.pdf', 120, 'application/pdf');

        $this->actingAs($tutor)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Lesson PDF',
            'type' => MaterialType::Pdf->value,
            'file' => $file,
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $material = LearningMaterial::query()->first();
        $this->assertSame(MaterialType::Pdf, $material->type);
        $this->assertSame('local', $material->disk);
        $this->assertNotNull($material->file_path);
        $this->assertStringNotContainsString('lesson notes', (string) $material->file_path);
        $this->assertStringEndsWith('.pdf', (string) $material->file_path);
        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_tutor_can_upload_powerpoint_material(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $file = UploadedFile::fake()->create(
            'slides.pptx',
            200,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );

        $this->actingAs($tutor)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Slides',
            'type' => MaterialType::Powerpoint->value,
            'file' => $file,
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $material = LearningMaterial::query()->first();
        $this->assertNotNull($material);
        $this->assertSame(MaterialType::Powerpoint, $material->type);
        $this->assertStringEndsWith('.pptx', (string) $material->file_path);
        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_tutor_can_add_external_url_material(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Docs',
            'type' => MaterialType::ExternalUrl->value,
            'external_url' => 'https://example.com/python-docs',
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $material = LearningMaterial::query()->first();
        $this->assertSame(MaterialType::ExternalUrl, $material->type);
        $this->assertSame('https://example.com/python-docs', $material->external_url);
    }

    public function test_tutor_can_reorder_materials(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $first = LearningMaterial::factory()->for($unit, 'learningUnit')->create(['title' => 'A', 'sort_order' => 0]);
        $second = LearningMaterial::factory()->for($unit, 'learningUnit')->create(['title' => 'B', 'sort_order' => 1]);

        $this->actingAs($tutor)->post(route('tutor.materials.reorder', [$course, $module, $unit]), [
            'order' => [$second->id, $first->id],
        ])->assertRedirect(route('tutor.units.edit', [$course, $module, $unit]));

        $this->assertSame(['B', 'A'], $unit->materials()->pluck('title')->all());
    }

    public function test_student_can_access_published_material(): void
    {
        [$tutor, $course, $module, $unit, $material] = $this->publishedPublicStack();
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('materials.show', [$course, $unit, $material]))
            ->assertOk()
            ->assertSee($material->title);
    }

    public function test_student_cannot_access_unpublished_material(): void
    {
        [$tutor, $course, $module, $unit, $material] = $this->publishedPublicStack();
        $material->update(['status' => MaterialStatus::Draft]);
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('materials.show', [$course, $unit, $material]))
            ->assertForbidden();
    }

    public function test_tutor_cannot_manage_another_tutors_materials(): void
    {
        [$owner, $course, $module, $unit] = $this->ownedUnit();
        $otherTutor = User::factory()->tutor()->create();

        $this->actingAs($otherTutor)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Intruder',
            'type' => MaterialType::RichText->value,
            'content' => 'Nope',
        ])->assertForbidden();
    }

    public function test_student_cannot_create_materials(): void
    {
        $student = User::factory()->student()->create();
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($student)->post(route('tutor.materials.store', [$course, $module, $unit]), [
            'title' => 'Student material',
            'type' => MaterialType::RichText->value,
            'content' => 'Nope',
        ])->assertForbidden();
    }

    public function test_executable_uploads_are_rejected(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();
        $file = UploadedFile::fake()->create('shell.php', 20, 'application/x-php');

        $this->actingAs($tutor)
            ->from(route('tutor.materials.create', [$course, $module, $unit]))
            ->post(route('tutor.materials.store', [$course, $module, $unit]), [
                'title' => 'Malware',
                'type' => MaterialType::Pdf->value,
                'file' => $file,
            ])
            ->assertRedirect(route('tutor.materials.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('file');

        $this->assertSame(0, LearningMaterial::query()->count());
    }

    public function test_external_url_must_be_http_or_https(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->from(route('tutor.materials.create', [$course, $module, $unit]))
            ->post(route('tutor.materials.store', [$course, $module, $unit]), [
                'title' => 'Bad URL',
                'type' => MaterialType::ExternalUrl->value,
                'external_url' => 'javascript:alert(1)',
            ])
            ->assertRedirect(route('tutor.materials.create', [$course, $module, $unit]))
            ->assertSessionHasErrors('external_url');
    }

    public function test_rich_text_requires_content(): void
    {
        [$tutor, $course, $module, $unit] = $this->ownedUnit();

        $this->actingAs($tutor)
            ->from(route('tutor.materials.create', [$course, $module, $unit]))
            ->post(route('tutor.materials.store', [$course, $module, $unit]), [
                'title' => 'Empty',
                'type' => MaterialType::RichText->value,
                'content' => '',
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_viewing_material_does_not_create_mastery_or_progress_state(): void
    {
        [$tutor, $course, $module, $unit, $material] = $this->publishedPublicStack();
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('materials.show', [$course, $unit, $material]))
            ->assertOk();

        $this->assertDatabaseMissing('learning_materials', [
            'id' => $material->id,
            'status' => 'mastered',
        ]);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('progress'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('masteries'));
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
     * @return array{0: User, 1: Course, 2: Module, 3: LearningUnit, 4: LearningMaterial}
     */
    private function publishedPublicStack(): array
    {
        $tutor = User::factory()->tutor()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->create(['status' => ModuleStatus::Published]);
        $unit = LearningUnit::factory()->for($module)->create(['status' => LearningUnitStatus::Published]);
        $material = LearningMaterial::factory()->for($unit, 'learningUnit')->published()->create([
            'title' => 'Published notes',
            'content' => 'Readable content',
        ]);

        return [$tutor, $course, $module, $unit, $material];
    }
}
