<?php

namespace Database\Factories;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\WeakAreaClassification;
use App\Models\Course;
use App\Models\ReassessmentCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReassessmentCandidate>
 */
class ReassessmentCandidateFactory extends Factory
{
    protected $model = ReassessmentCandidate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'course_id' => Course::factory(),
            'candidate_key' => hash('sha256', fake()->uuid()),
            'research_learner_id' => hash('sha256', 'research'),
            'learning_area_key' => 'concept:loops',
            'learning_area_label' => 'loops',
            'learning_area_representation' => 'activity_concept',
            'weak_area_classification' => WeakAreaClassification::WeakPersistent,
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'status' => ReassessmentCandidateStatus::EligiblePendingGeneration,
            'specification' => [
                'concept' => 'loops',
                'bloom_demand' => 'apply',
                'provenance' => [
                    'learning_state_ids' => [1],
                    'validated_evidence_ids' => [1],
                ],
            ],
            'ai_safe_payload' => null,
            'candidate_content' => null,
            'generator_identity' => null,
            'generator_model' => null,
            'generation_metadata' => null,
            'validation_result' => null,
            'validation_errors' => null,
            'failure_reason' => null,
            'generated_at' => null,
            'validated_at' => null,
        ];
    }
}
