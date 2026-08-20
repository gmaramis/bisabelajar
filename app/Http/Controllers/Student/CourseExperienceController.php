<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LearningUnit;
use App\Models\Module;
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

    public function showModule(Course $course, Module $module): View
    {
        $this->ensureModuleBelongsToCourse($course, $module);
        $this->authorize('learn', $module);

        $module->load([
            'learningUnits' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        return view('student.modules.show', [
            'course' => $course,
            'module' => $module,
        ]);
    }

    public function showUnit(Course $course, Module $module, LearningUnit $learningUnit): View
    {
        $this->ensureUnitBelongsToModule($course, $module, $learningUnit);
        $this->authorize('learn', $learningUnit);

        $learningUnit->load([
            'materials' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        return view('student.units.show', [
            'course' => $course,
            'module' => $module,
            'learningUnit' => $learningUnit,
        ]);
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
