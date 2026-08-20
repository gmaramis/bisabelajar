<?php

namespace App\Http\Requests\Tutor;

use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderLearningMaterialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningUnit = $this->route('learningUnit');

        return $learningUnit instanceof LearningUnit
            && ($this->user()?->can('reorder', [LearningMaterial::class, $learningUnit]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $learningUnit = $this->route('learningUnit');
        $unitId = $learningUnit instanceof LearningUnit ? $learningUnit->id : 0;

        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'integer',
                Rule::exists('learning_materials', 'id')->where('learning_unit_id', $unitId),
            ],
        ];
    }
}
