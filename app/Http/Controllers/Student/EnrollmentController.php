<?php

namespace App\Http\Controllers\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreEnrollmentRequest;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function store(StoreEnrollmentRequest $request, Course $course): RedirectResponse
    {
        try {
            Enrollment::query()->create([
                'user_id' => $request->user()->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active,
                'enrolled_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return redirect()
                ->route('courses.show', $course)
                ->withErrors(['course' => 'You are already enrolled in this course.']);
        }

        return redirect()
            ->route('student.learning')
            ->with('status', 'Enrolled in course.');
    }
}
