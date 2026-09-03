<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class AiHintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'error_message' => ['nullable', 'string', 'max:1000'],
            'test_case_label' => ['nullable', 'string', 'max:200'],
            'attempt_count' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{error_message: string|null, test_case_label: string|null, attempt_count: int}
     */
    public function attemptContext(): array
    {
        return [
            'error_message' => $this->input('error_message'),
            'test_case_label' => $this->input('test_case_label'),
            'attempt_count' => (int) $this->input('attempt_count', 1),
        ];
    }
}
