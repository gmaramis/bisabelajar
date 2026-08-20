<?php

namespace Database\Factories;

use App\Enums\ProgressStatus;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityProgress>
 */
class ActivityProgressFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (ActivityProgress $progress): void {
            if ($progress->user_id !== null) {
                return;
            }

            $progress->user_id = $progress->enrollment?->user_id
                ?? Enrollment::query()->find($progress->enrollment_id)?->user_id;
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
            'status' => ProgressStatus::NotStarted,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProgressStatus::InProgress,
            'started_at' => now(),
        ]);
    }
}
