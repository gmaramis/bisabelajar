<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\ModuleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderModulesRequest;
use App\Http\Requests\Tutor\StoreModuleRequest;
use App\Http\Requests\Tutor\UpdateModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function create(Course $course): View
    {
        $this->authorize('create', [Module::class, $course]);

        return view('tutor.modules.create', [
            'course' => $course,
        ]);
    }

    public function store(StoreModuleRequest $request, Course $course): RedirectResponse
    {
        $sortOrder = (int) $course->modules()->max('sort_order') + 1;

        $course->modules()->create([
            ...$request->validated(),
            'sort_order' => $sortOrder,
            'status' => ModuleStatus::Draft,
        ]);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Module created.');
    }

    public function edit(Course $course, Module $module): View
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('update', $module);

        return view('tutor.modules.edit', [
            'course' => $course,
            'module' => $module,
        ]);
    }

    public function update(UpdateModuleRequest $request, Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);

        $module->update($request->validated());

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Module updated.');
    }

    public function destroy(Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('delete', $module);

        $module->delete();

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Module deleted.');
    }

    public function reorder(ReorderModulesRequest $request, Course $course): RedirectResponse
    {
        foreach ($request->validated('order') as $index => $moduleId) {
            Module::query()
                ->where('course_id', $course->id)
                ->where('id', $moduleId)
                ->update(['sort_order' => $index]);
        }

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Modules reordered.');
    }

    public function publish(Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('publish', $module);

        $module->update([
            'status' => ModuleStatus::Published,
        ]);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Module published.');
    }

    public function unpublish(Course $course, Module $module): RedirectResponse
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('unpublish', $module);

        $module->update([
            'status' => ModuleStatus::Draft,
        ]);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Module unpublished.');
    }

    private function ensureModuleBelongsToCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }
}
