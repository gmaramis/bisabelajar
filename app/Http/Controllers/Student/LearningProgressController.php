<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LearningProgressController extends Controller
{
    public function complete(
        Request $request,
        Course $course,
        Module $module,
        LearningUnit $learningUnit,
    ): RedirectResponse {
        abort_unless($module->course_id === $course->id, 404);
        abort_unless($learningUnit->module_id === $module->id, 404);
        $this->authorize('update', [LearningProgress::class, $learningUnit]);

        $enrollment = $this->activeEnrollment($request, $course);
        LearningProgress::markCompleted($enrollment, $learningUnit);

        return redirect()
            ->route('student.units.show', [$course, $module, $learningUnit])
            ->with('status', 'Unit marked complete. Completion is not mastery.');
    }

    private function activeEnrollment(Request $request, Course $course): Enrollment
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
