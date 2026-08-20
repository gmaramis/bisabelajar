<?php

namespace App\Http\Requests\Tutor;

use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderLearningUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = $this->route('module');

        return $module instanceof Module
            && ($this->user()?->can('reorder', [LearningUnit::class, $module]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $module = $this->route('module');
        $moduleId = $module instanceof Module ? $module->id : 0;

        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('learning_units', 'id')->where('module_id', $moduleId),
            ],
        ];
    }
}
