<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivitySubmission>
 */
class ActivitySubmissionFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (ActivitySubmission $submission): void {
            if ($submission->user_id !== null) {
                return;
            }

            $submission->user_id = $submission->enrollment?->user_id
                ?? Enrollment::query()->find($submission->enrollment_id)?->user_id;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'activity_id' => Activity::factory(),
            'attempt_number' => 1,
            'version' => 1,
            'status' => SubmissionStatus::Submitted,
            'payload' => ['body' => fake()->paragraph()],
            'submitted_at' => now(),
        ];
    }
}
