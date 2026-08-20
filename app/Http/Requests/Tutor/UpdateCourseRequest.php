<?php

namespace App\Http\Requests\Tutor;

use App\Enums\CourseVisibility;
use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && ($this->user()?->can('update', $course) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->route('course');
        $courseId = $course instanceof Course ? $course->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('courses', 'slug')->ignore($courseId),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'visibility' => ['required', new Enum(CourseVisibility::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $course = $this->route('course');
            $ignoreId = $course instanceof Course ? $course->id : null;

            $this->merge([
                'slug' => Course::uniqueSlug($this->string('slug')->toString(), $ignoreId),
            ]);
        }
    }
}
