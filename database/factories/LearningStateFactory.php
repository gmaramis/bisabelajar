<?php

namespace Database\Factories;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\LearningState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningState>
 */
class LearningStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'activity_id' => Activity::factory(),
            'inference_key' => hash('sha256', fake()->uuid()),
            'state' => LearningStateValue::InsufficientEvidence,
            'state_confidence' => StateConfidence::Low,
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'cognitive_indicator' => null,
            'psychomotor_indicator' => null,
            'behavioral_indicators' => [],
            'fusion_summary' => [
                'usable_count' => 0,
                'excluded_uncertain_count' => 0,
            ],
            'explanation' => 'Insufficient validated evidence for a responsible learning-state inference.',
            'inference_rule' => 'insufficient_evidence_minimal_usable',
            'inferred_at' => now(),
        ];
    }
}
