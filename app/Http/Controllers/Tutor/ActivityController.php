<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\ReorderActivitiesRequest;
use App\Http\Requests\Tutor\StoreActivityRequest;
use App\Http\Requests\Tutor\UpdateActivityRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Support\ActivityConfiguration;
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
            'activity' => null,
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

        $type = ActivityType::from($request->validated('type'));

        $learningUnit->activities()->create([
            'title' => $request->validated('title'),
            'type' => $type,
            'status' => ActivityStatus::Draft,
            'sort_order' => (int) $learningUnit->activities()->max('sort_order') + 1,
            'configuration' => ActivityConfiguration::normalize(
                $type,
                $request->validated('configuration'),
            ),
        ]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity added.');
    }

    public function edit(Course $course, Module $module, LearningUnit $learningUnit, Activity $activity): View
    {
        $this->ensureActivityNesting($course, $module, $learningUnit, $activity);
        $this->authorize('update', $activity);

        return view('tutor.activities.edit', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit,
            'activity' => $activity,
            'types' => ActivityType::cases(),
        ]);
    }

    public function update(
        UpdateActivityRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        $this->ensureActivityNesting($course, $module, $learningUnit, $activity);

        $type = ActivityType::from($request->validated('type'));

        $activity->update([
            'title' => $request->validated('title'),
            'type' => $type,
            'configuration' => ActivityConfiguration::normalize(
                $type,
                $request->validated('configuration'),
            ),
        ]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity updated.');
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

    public function publish(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        $this->ensureActivityNesting($course, $module, $learningUnit, $activity);
        $this->authorize('publish', $activity);

        $activity->update(['status' => ActivityStatus::Published]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity published.');
    }

    public function unpublish(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        $this->ensureActivityNesting($course, $module, $learningUnit, $activity);
        $this->authorize('unpublish', $activity);

        $activity->update(['status' => ActivityStatus::Draft]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity unpublished.');
    }

    public function archive(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        $this->ensureActivityNesting($course, $module, $learningUnit, $activity);
        $this->authorize('archive', $activity);

        $activity->update(['status' => ActivityStatus::Archived]);

        return redirect()
            ->route('tutor.units.edit', [$course, $module, $learningUnit])
            ->with('status', 'Activity archived.');
    }

    private function ensureNesting(Course $course, Module $module, LearningUnit $learningUnit): void
    {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
    }

    private function ensureActivityNesting(
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): void {
        $this->ensureNesting($course, $module, $learningUnit);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);
    }
}
