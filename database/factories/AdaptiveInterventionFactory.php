<?php

namespace Database\Factories;

use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\LearningState;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdaptiveIntervention>
 */
class AdaptiveInterventionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'activity_id' => Activity::factory(),
            'learning_state_id' => LearningState::factory(),
            'intervention_key' => hash('sha256', fake()->uuid()),
            'intervention_type' => InterventionType::Reinforcement,
            'socratic_type' => null,
            'target_state' => LearningStateValue::Stable,
            'content' => 'Keep going with the current approach.',
            'reason' => 'Stable learning state supports brief reinforcement.',
            'selection_rule' => 'stable_reinforcement',
            'is_strong' => false,
            'is_remedial' => false,
            'metadata' => [],
        ];
    }
}
