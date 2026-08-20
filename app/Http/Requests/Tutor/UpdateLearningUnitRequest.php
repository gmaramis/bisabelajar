<?php

namespace App\Http\Requests\Tutor;

use App\Models\LearningUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningUnit = $this->route('learning_unit') ?? $this->route('learningUnit');

        return $learningUnit instanceof LearningUnit
            && ($this->user()?->can('update', $learningUnit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $learningUnit = $this->route('learning_unit') ?? $this->route('learningUnit');
        $unitId = $learningUnit instanceof LearningUnit ? $learningUnit->id : null;
        $moduleId = $learningUnit instanceof LearningUnit ? $learningUnit->module_id : 0;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('learning_units', 'slug')->where('module_id', $moduleId)->ignore($unitId),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $learningUnit = $this->route('learning_unit') ?? $this->route('learningUnit');

        if ($this->filled('slug') && $learningUnit instanceof LearningUnit) {
            $this->merge([
                'slug' => LearningUnit::uniqueSlug(
                    $learningUnit->module_id,
                    $this->string('slug')->toString(),
                    $learningUnit->id,
                ),
            ]);
        }
    }
}
