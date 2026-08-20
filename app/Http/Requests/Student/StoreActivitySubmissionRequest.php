<?php

namespace App\Http\Requests\Student;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ActivitySubmission;
use App\Support\ActivitySubmissionPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreActivitySubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            && ($this->user()?->can('submit', $activity) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ActivitySubmissionPayload::rules();
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->input('payload');

        if (! is_array($payload)) {
            return;
        }

        $this->merge([
            'payload' => ActivitySubmissionPayload::prune($payload),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $activity = $this->route('activity');

            if (! $activity instanceof Activity) {
                return;
            }

            ActivitySubmissionPayload::validateForActivity($validator, $activity);

            if (! $this->hasStarted($activity)) {
                $validator->errors()->add('payload', 'Start the activity before submitting.');

                return;
            }

            if ($this->attemptCount($activity) >= $activity->maxAttempts()) {
                $validator->errors()->add('payload', 'No remaining attempts are allowed for this activity.');
            }
        });
    }

    private function hasStarted(Activity $activity): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $progress = ActivityProgress::query()
            ->where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        return $progress instanceof ActivityProgress && $progress->isStarted();
    }

    private function attemptCount(Activity $activity): int
    {
        $user = $this->user();

        if ($user === null) {
            return 0;
        }

        return ActivitySubmission::query()
            ->where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->count();
    }
}
