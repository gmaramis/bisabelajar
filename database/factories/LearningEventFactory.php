<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningEvent>
 */
class LearningEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'activity_id' => Activity::factory(),
            'event_type' => 'activity_started',
            'payload' => [],
            'occurred_at' => now(),
            'session_id' => null,
        ];
    }
}
