<?php

namespace App\Http\Requests\Tutor;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\LearningUnit;
use App\Support\ActivityConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

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
        return array_merge([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ActivityType::class)],
        ], ActivityConfiguration::rules());
    }

    protected function prepareForValidation(): void
    {
        $configuration = $this->input('configuration');

        if (! is_array($configuration)) {
            return;
        }

        $this->merge([
            'configuration' => ActivityConfiguration::prune($configuration),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = ActivityType::tryFrom((string) $this->input('type'));

            if ($type === null) {
                return;
            }

            ActivityConfiguration::validateForType($validator, $type);
        });
    }
}
