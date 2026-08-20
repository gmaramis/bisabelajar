<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningUnit;
use Illuminate\View\View;

class ActivityAccessController extends Controller
{
    public function show(Course $course, LearningUnit $learningUnit, Activity $activity): View
    {
        $this->ensurePublishedAccess($course, $learningUnit, $activity);
        $this->authorize('view', $activity);

        $learningUnit->loadMissing('module');

        return view('activities.show', [
            'course' => $course,
            'module' => $learningUnit->module,
            'learningUnit' => $learningUnit,
            'activity' => $activity,
            'configuration' => $activity->studentSafeConfiguration(),
        ]);
    }

    private function ensurePublishedAccess(Course $course, LearningUnit $learningUnit, Activity $activity): void
    {
        abort_unless($learningUnit->module?->course_id === $course->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);
    }
}
