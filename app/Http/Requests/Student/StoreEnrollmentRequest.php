<?php

namespace App\Http\Requests\Student;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course
            && ($this->user()?->can('create', [Enrollment::class, $course]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $course = $this->route('course');
            $user = $this->user();

            if (! $course instanceof Course || $user === null) {
                return;
            }

            $alreadyEnrolled = Enrollment::query()
                ->where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if ($alreadyEnrolled) {
                $validator->errors()->add('course', 'You are already enrolled in this course.');
            }
        });
    }
}
