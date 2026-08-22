<?php

namespace Database\Factories;

use App\Enums\NextLearningActionType;
use App\Models\Activity;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NextLearningAction>
 */
class NextLearningActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'activity_id' => Activity::factory(),
            'learning_state_id' => LearningState::factory(),
            'adaptive_intervention_id' => null,
            'decision_key' => hash('sha256', fake()->uuid()),
            'action' => NextLearningActionType::Continue,
            'reason' => 'Seeded next learning action.',
            'decision_rule' => 'stable_continue',
            'retry_outcome' => null,
            'metadata' => [],
            'decided_at' => now(),
        ];
    }
}
