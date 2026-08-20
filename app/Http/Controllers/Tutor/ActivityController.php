<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderActivitiesRequest;
use App\Http\Requests\Tutor\StoreActivityRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function create(Course $course, Module $module, LearningUnit $learningUnit): View
    {
        $this->ensureNesting($course, $module, $learningUnit);
        $this->authorize('create', [Activity::class, $learningUnit]);

        return view('tutor.activities.create', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit,
            'types' => ActivityType::cases(),
        ]);
    }

    public function store(
        StoreActivityRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        $this->ensureNesting($course, $module, $learningUnit);

        $learningUnit->activities()->create([
            'title' => $request->validated('title'),
            'type' => ActivityType::from($request->validated('type')),
            'status' => ActivityStatus::Draft,
            'sort_order' => (int) $learningUnit->activities()->max('sort_order') + 1,
            'configuration' => [],
        ]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity added.');
    }

    public function reorder(
        ReorderActivitiesRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        $this->ensureNesting($course, $module, $learningUnit);

        foreach ($request->validated('order') as $index => $activityId) {
            Activity::query()
                ->where('learning_unit_id', $learningUnit->id)
                ->where('id', $activityId)
                ->update(['sort_order' => $index]);
        }

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activities reordered.');
    }

    private function ensureNesting(Course $course, Module $module, LearningUnit $learningUnit): void
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
    }
}
