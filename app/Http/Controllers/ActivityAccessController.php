<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\ProgrammingActivity;
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
        $programmingActivity = null;
        $availableProfiles = collect();

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

            // Check if this is a programming activity
            if ($activity->type === \App\Enums\ActivityType::CodingExercise) {
                $programmingActivity = $activity->programmingActivity;
                if ($programmingActivity) {
                    $availableProfiles = \App\Models\LanguageExecutionProfile::where('enabled', true)->get();
                }
            }
        }

        // Use programming view for coding exercises
        if ($activity->type === \App\Enums\ActivityType::CodingExercise && $programmingActivity) {
            return view('activities.programming', [
                'course' => $course,
                'module' => $learningUnit->module,
                'learningUnit' => $learningUnit,
                'activity' => $activity,
                'configuration' => $activity->studentSafeConfiguration(),
                'activityProgress' => $activityProgress,
                'submissions' => $submissions,
                'programmingActivity' => [
                    'id' => $programmingActivity->id,
                    'starter_code' => $programmingActivity->starter_code,
                    'editable_files' => $programmingActivity->getEditableFiles(),
                    'execution_time_limit_seconds' => $programmingActivity->getExecutionTimeLimitSeconds(),
                    'memory_limit_mb' => $programmingActivity->getMemoryLimitMb(),
                    'source_code_size_limit_kb' => $programmingActivity->getSourceCodeSizeLimitKb(),
                    'language_execution_profile_id' => $programmingActivity->language_execution_profile_id,
                ],
                'availableProfiles' => $availableProfiles,
            ]);
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
