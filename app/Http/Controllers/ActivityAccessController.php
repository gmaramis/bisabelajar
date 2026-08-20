<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\LearningUnit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityAccessController extends Controller
{
    public function show(Request $request, Course $course, LearningUnit $learningUnit, Activity $activity): View
    {
        $this->ensurePublishedAccess($course, $learningUnit, $activity);
        $this->authorize('view', $activity);

        $learningUnit->loadMissing('module');

        $activityProgress = null;
        $submissions = collect();
        if ($request->user()?->isStudent()) {
            $activityProgress = ActivityProgress::query()
                ->where('user_id', $request->user()->id)
                ->where('activity_id', $activity->id)
                ->first();
            $submissions = ActivitySubmission::query()
                ->where('user_id', $request->user()->id)
                ->where('activity_id', $activity->id)
                ->orderBy('attempt_number')
                ->get();
        }

        return view('activities.show', [
            'course' => $course,
            'module' => $learningUnit->module,
            'learningUnit' => $learningUnit,
            'activity' => $activity,
            'configuration' => $activity->studentSafeConfiguration(),
            'activityProgress' => $activityProgress,
            'submissions' => $submissions,
        ]);
    }

    private function ensurePublishedAccess(Course $course, LearningUnit $learningUnit, Activity $activity): void
    {
        abort_unless($learningUnit->module?->course_id === $course->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);
    }
}
