<?php

namespace App\Http\Requests\Student;

use App\Enums\CompletionRule;
use App\Models\Activity;
use App\Models\ActivitySubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            && ($this->user()?->can('complete', $activity) ?? false);
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
            $activity = $this->route('activity');

            if (! $activity instanceof Activity) {
                return;
            }

            if ($activity->completionRule() !== CompletionRule::Submission) {
                return;
            }

            $user = $this->user();
            if ($user === null) {
                return;
            }

            $hasSubmission = ActivitySubmission::query()
                ->where('user_id', $user->id)
                ->where('activity_id', $activity->id)
                ->exists();

            if (! $hasSubmission) {
                $validator->errors()->add('completion', 'A valid submission is required before this activity can be completed.');
            }
        });
    }
}
