<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreActivitySubmissionRequest;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Support\ActivitySubmissionPayload;
use Illuminate\Http\RedirectResponse;

class ActivitySubmissionController extends Controller
{
    public function store(
        StoreActivitySubmissionRequest $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
        Activity $activity,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
        abort_unless($activity->learning_unit_id === $learningUnit->id, 404);

        $enrollment = $this->activeEnrollment($request, $course);
        $attempt = $activity->submissions()->where('user_id', $request->user()->id)->count() + 1;

        $activity->submissions()->create([
            'enrollment_id' => $enrollment->id,
            'user_id' => $request->user()->id,
            'attempt_number' => $attempt,
            'version' => $attempt,
            'status' => SubmissionStatus::Submitted,
            'payload' => ActivitySubmissionPayload::normalize(
                $activity->type,
                $request->validated('payload'),
            ),
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('activities.show', [$course, $learningUnit, $activity])
            ->with('status', 'Submission saved. This is not a grade or mastery result.');
    }

    private function activeEnrollment(StoreActivitySubmissionRequest $request, Course $course): Enrollment
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
