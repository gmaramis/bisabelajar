<?php

namespace App\Http\Controllers\Tutor;

use App\Enums\CourseStatus;
use App\Enums\CourseVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tutor\StoreCourseRequest;
use App\Http\Requests\Tutor\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Course::class);

        $courses = $request->user()
            ->courses()
            ->latest()
            ->get();

        return view('tutor.courses.index', [
            'user' => $request->user(),
            'courses' => $courses,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Course::class);

        return view('tutor.courses.create', [
            'visibilities' => CourseVisibility::cases(),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['owner_id'] = $request->user()->id;
        $data['status'] = CourseStatus::Draft;
        $data['slug'] = Course::uniqueSlug($data['slug'] ?? $data['title']);

        $course = Course::query()->create($data);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Course created.');
    }

    public function edit(Course $course): View
    {
        $this->authorize('update', $course);

        return view('tutor.courses.edit', [
            'course' => $course->load('modules'),
            'visibilities' => CourseVisibility::cases(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Course::uniqueSlug($data['slug'] ?? $data['title'], $course->id);

        $course->update($data);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Course updated.');
    }

    public function publish(Course $course): RedirectResponse
    {
        $this->authorize('publish', $course);

        $course->update([
            'status' => CourseStatus::Published,
        ]);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Course published.');
    }

    public function archive(Course $course): RedirectResponse
    {
        $this->authorize('archive', $course);

        $course->update([
            'status' => CourseStatus::Archived,
        ]);

        return redirect()
            ->route('tutor.courses.edit', $course)
            ->with('status', 'Course archived.');
    }
}
