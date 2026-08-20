<?php

namespace Database\Factories;

use App\Enums\MaterialStatus;
use App\Enums\MaterialType;
use App\Models\LearningMaterial;
use App\Models\LearningUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterial>
 */
class LearningMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_unit_id' => LearningUnit::factory(),
            'title' => fake()->sentence(3),
            'type' => MaterialType::RichText,
            'content' => fake()->paragraph(),
            'external_url' => null,
            'file_path' => null,
            'disk' => null,
            'mime_type' => null,
            'sort_order' => 0,
            'status' => MaterialStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MaterialStatus::Published,
        ]);
    }

    public function pdf(string $path = 'learning-materials/test.pdf'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaterialType::Pdf,
            'content' => null,
            'file_path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function externalUrl(string $url = 'https://example.com/resource'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaterialType::ExternalUrl,
            'content' => null,
            'external_url' => $url,
        ]);
    }
}
