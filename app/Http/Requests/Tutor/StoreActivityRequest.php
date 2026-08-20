<?php

namespace App\Http\Requests\Tutor;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\LearningUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningUnit = $this->route('learningUnit');

        return $learningUnit instanceof LearningUnit
            && ($this->user()?->can('create', [Activity::class, $learningUnit]) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ActivityType::class)],
        ];
    }
}
