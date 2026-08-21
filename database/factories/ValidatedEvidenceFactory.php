<?php

namespace Database\Factories;

use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Models\Activity;
use App\Models\LearningEvent;
use App\Models\User;
use App\Models\ValidatedEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValidatedEvidence>
 */
class ValidatedEvidenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'activity_id' => Activity::factory(),
            'learning_event_id' => LearningEvent::factory(),
            'source_record_type' => null,
            'source_record_id' => null,
            'evidence_category' => EvidenceCategory::Interaction,
            'evidence_type' => 'activity_started',
            'observed_value' => ['summary' => 'Activity started'],
            'context_summary' => [
                'task_repetition' => 'new',
                'task_difficulty' => 'unknown',
                'execution_anomaly' => 'unknown',
                'network_environment' => 'unknown',
            ],
            'quality' => EvidenceQuality::Valid,
            'confidence' => EvidenceConfidence::Medium,
            'validation_reason' => 'Learner interaction event is observable and recorded without inferring a learning state.',
            'validated_at' => now(),
        ];
    }
}
