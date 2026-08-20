<?php

namespace App\Http\Requests\Tutor;

use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = $this->route('module');

        return $module instanceof Module
            && ($this->user()?->can('create', [LearningUnit::class, $module]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $module = $this->route('module');
        $moduleId = $module instanceof Module ? $module->id : 0;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('learning_units', 'slug')->where('module_id', $moduleId),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $module = $this->route('module');

        if ($this->filled('slug') && $module instanceof Module) {
            $this->merge([
                'slug' => LearningUnit::uniqueSlug($module->id, $this->string('slug')->toString()),
            ]);
        }
    }
}
