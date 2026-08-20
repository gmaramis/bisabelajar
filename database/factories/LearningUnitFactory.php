<?php

namespace Database\Factories;

use App\Enums\LearningUnitStatus;
use App\Models\LearningUnit;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LearningUnit>
 */
class LearningUnitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'module_id' => Module::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->paragraph(),
            'sort_order' => 0,
            'status' => LearningUnitStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LearningUnitStatus::Published,
        ]);
    }
}
