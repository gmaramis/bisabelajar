<?php

namespace App\Http\Requests\Tutor;

use App\Models\Activity;
use App\Models\LearningUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningUnit = $this->route('learningUnit');

        return $learningUnit instanceof LearningUnit
            && ($this->user()?->can('reorder', [Activity::class, $learningUnit]) ?? false);
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
                Rule::exists('activities', 'id')->where('learning_unit_id', $unitId),
            ],
        ];
    }
}
