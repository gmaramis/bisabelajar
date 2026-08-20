<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseExperienceController extends Controller
{
    public function showCourse(Course $course): View
    {
        $this->authorize('learn', $course);

        $course->load([
            'modules' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        return view('student.courses.show', [
            'course' => $course,
        ]);
    }

    public function showModule(Request $request, Course $course, Module $module): View
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('learn', $module);

        $module->load([
            'learningUnits' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        $progressByUnitId = LearningProgress::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('learning_unit_id', $module->learningUnits->pluck('id'))
            ->get()
            ->keyBy('learning_unit_id');

        return view('student.modules.show', [
            'course' => $course,
            'module' => $module,
            'progressByUnitId' => $progressByUnitId,
        ]);
    }

    public function showUnit(Request $request, Course $course, Module $module, LearningUnit $learningUnit): View
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('learn', $learningUnit);

        $learningUnit->load([
            'materials' => fn ($query) => $query->published()->orderBy('sort_order'),
            'activities' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        $progress = LearningProgress::markInProgress(
            $this->activeEnrollment($request, $course),
            $learningUnit,
        );

        $activityProgressById = ActivityProgress::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('activity_id', $learningUnit->activities->pluck('id'))
            ->get()
            ->keyBy('activity_id');

        return view('student.units.show', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit,
            'progress' => $progress,
            'activityProgressById' => $activityProgressById,
        ]);
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
