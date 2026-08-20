<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderLearningMaterialsRequest;
use App\Http\Requests\Tutor\StoreLearningMaterialRequest;
use App\Models\Course;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LearningMaterialController extends Controller
{
    public function create(Course $course, Module $module, LearningUnit $learningUnit): View
    {
        $this->ensureNesting($course, $module, $learningUnit);
        $this->authorize('create', [LearningMaterial::class, $learningUnit]);

        return view('tutor.materials.create', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit,
            'types' => MaterialType::cases(),
        ]);
    }

    public function store(
        StoreLearningMaterialRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        $this->ensureNesting($course, $module, $learningUnit);

        $type = MaterialType::from($request->validated('type'));
        $attributes = [
            'title' => $request->validated('title'),
            'type' => $type,
            'content' => null,
            'external_url' => null,
            'file_path' => null,
            'disk' => null,
            'mime_type' => null,
            'sort_order' => (int) $learningUnit->materials()->max('sort_order') + 1,
            'status' => MaterialStatus::Draft,
        ];

        if ($type === MaterialType::RichText) {
            $attributes['content'] = $request->validated('content');
        }

        if ($type === MaterialType::ExternalUrl) {
            $attributes['external_url'] = $request->validated('external_url');
        }

        if ($type === MaterialType::Pdf || $type === MaterialType::Powerpoint) {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $extension = $this->storedExtension($file, $type);
            $path = $file->storeAs(
                'learning-materials/'.$learningUnit->id,
                Str::uuid()->toString().'.'.$extension,
                'local',
            );

            $attributes['file_path'] = $path;
            $attributes['disk'] = 'local';
            $attributes['mime_type'] = $file->getMimeType();
        }

        $learningUnit->materials()->create($attributes);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Material added.');
    }

    public function destroy(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): RedirectResponse {
        $this->ensureMaterialNesting($course, $module, $learningUnit, $material);
        $this->authorize('delete', $material);

        $material->deleteStoredFile();
        $material->delete();

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Material deleted.');
    }

    public function reorder(
        ReorderLearningMaterialsRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        $this->ensureNesting($course, $module, $learningUnit);

        foreach ($request->validated('order') as $index => $materialId) {
            LearningMaterial::query()
                ->where('learning_unit_id', $learningUnit->id)
                ->where('id', $materialId)
                ->update(['sort_order' => $index]);
        }

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Materials reordered.');
    }

    public function publish(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): RedirectResponse {
        $this->ensureMaterialNesting($course, $module, $learningUnit, $material);
        $this->authorize('publish', $material);

        $material->update(['status' => MaterialStatus::Published]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Material published.');
    }

    public function unpublish(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): RedirectResponse {
        $this->ensureMaterialNesting($course, $module, $learningUnit, $material);
        $this->authorize('unpublish', $material);

        $material->update(['status' => MaterialStatus::Draft]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Material unpublished.');
    }

    private function storedExtension(UploadedFile $file, MaterialType $type): string
    {
        return match ($type) {
            MaterialType::Pdf => 'pdf',
            MaterialType::Powerpoint => strtolower((string) $file->getClientOriginalExtension()) === 'ppt'
                ? 'ppt'
                : 'pptx',
            default => abort(422, 'Unsupported upload type.'),
        };
    }

    private function ensureNesting(Course $course, Module $module, LearningUnit $learningUnit): void
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
    }

    private function ensureMaterialNesting(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        LearningMaterial $material,
    ): void {
        $this->ensureNesting($course, $module, $learningUnit);
        abort_unless($material->learning_unit_id === $learningUnit->id, 404);
    }
}
