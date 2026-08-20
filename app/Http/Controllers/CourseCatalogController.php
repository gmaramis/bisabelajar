<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseCatalogController extends Controller
{
    public function index(): View
    {
        $courses = Course::query()
            ->discoverable()
            ->latest()
            ->get();

        return view('courses.index', [
            'courses' => $courses,
        ]);
    }

    public function show(Request $request, Course $course): View
    {
        $user = $request->user();

        abort_unless(
            $course->isPubliclyViewable() || ($user !== null && $course->isOwnedBy($user)),
            403,
        );

        return view('courses.show', [
            'course' => $course,
        ]);
    }
}
