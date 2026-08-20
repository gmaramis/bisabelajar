<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory()->published()->public(),
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Active,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Completed,
        ]);
    }

    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Dropped,
        ]);
    }
}
