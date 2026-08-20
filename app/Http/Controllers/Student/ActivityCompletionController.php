<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CompleteActivityRequest;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;

class ActivityCompletionController extends Controller
{
    public function complete(
        CompleteActivityRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);

        $enrollment = $this->activeEnrollment($request, $course);
        ActivityProgress::markCompleted($enrollment, $activity);

        return redirect()
            ->route('activities.show', [$course, $learningUnit, $activity])
            ->with('status', 'Activity marked complete. Completion is not unit progress or mastery.');
    }

    private function activeEnrollment(CompleteActivityRequest $request, Course $course): Enrollment
    {
        $enrollment = $request->user()
            ->enrollments()
            ->where('course_id', $course->id)
            ->where('status', EnrollmentStatus::Active)
            ->first();

        abort_unless($enrollment instanceof Enrollment, 403);

        return $enrollment;
    }
}
