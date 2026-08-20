<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\LearningUnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderLearningUnitsRequest;
use App\Http\Requests\Tutor\StoreLearningUnitRequest;
use App\Http\Requests\Tutor\UpdateLearningUnitRequest;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LearningUnitController extends Controller
{
    public function create(Course $course, Module $module): View
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('create', [LearningUnit::class, $module]);

        return view('tutor.learning-units.create', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => null,
        ]);
    }

    public function store(StoreLearningUnitRequest $request, Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);

        $data = $request->validated();
        $data['sort_order'] = (int) $module->learningUnits()->max('sort_order') + 1;
        $data['status'] = LearningUnitStatus::Draft;
        $data['slug'] = LearningUnit::uniqueSlug($module->id, $data['slug'] ?? $data['title']);

        $module->learningUnits()->create($data);

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning unit created.');
    }

    public function edit(Course $course, Module $module, LearningUnit $learningUnit): View
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('update', $learningUnit);

        return view('tutor.learning-units.edit', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit->load(['materials', 'activities']),
        ]);
    }

    public function update(
        UpdateLearningUnitRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);

        $data = $request->validated();
        $data['slug'] = LearningUnit::uniqueSlug(
            $module->id,
            $data['slug'] ?? $data['title'],
            $learningUnit->id,
        );

        $learningUnit->update($data);

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning unit updated.');
    }

    public function destroy(Course $course, Module $module, LearningUnit $learningUnit): RedirectResponse
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('delete', $learningUnit);

        $learningUnit->delete();

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning unit deleted.');
    }

    public function reorder(ReorderLearningUnitsRequest $request, Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);

        foreach ($request->validated('order') as $index => $unitId) {
            LearningUnit::query()
                ->where('module_id', $module->id)
                ->where('id', $unitId)
                ->update(['sort_order' => $index]);
        }

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning units reordered.');
    }

    public function publish(Course $course, Module $module, LearningUnit $learningUnit): RedirectResponse
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('publish', $learningUnit);

        $learningUnit->update([
            'status' => LearningUnitStatus::Published,
        ]);

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning unit published.');
    }

    public function unpublish(Course $course, Module $module, LearningUnit $learningUnit): RedirectResponse
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('unpublish', $learningUnit);

        $learningUnit->update([
            'status' => LearningUnitStatus::Draft,
        ]);

        return redirect()
            ->route('tutor.modules.edit', [$course, $module])
            ->with('status', 'Learning unit unpublished.');
    }

    private function ensureModuleBelongsToCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    private function ensureUnitBelongsToModule(Course $course, Module $module, LearningUnit $learningUnit): void
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        abort_unless($learningUnit->module_id === $module->id, 404);
    }
}
