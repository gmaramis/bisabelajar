<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearningStateInferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private LearningStateInferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($this->course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $this->activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'difficulty' => 'medium',
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $this->service = app(LearningStateInferenceService::class);
    }

    public function test_all_revised_bloom_levels_are_supported(): void
    {
        foreach (BloomLevel::cases() as $level) {
            $this->activity->update(['bloom_demand' => $level]);
            $this->assertSame($level, $this->activity->fresh()->getBloomDemand());
            $this->assertContains($level->value, ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create']);
        }
    }

    public function test_all_dave_levels_are_supported(): void
    {
        foreach (DaveLevel::cases() as $level) {
            $this->activity->update(['dave_demand' => $level]);
            $this->assertSame($level, $this->activity->fresh()->getDaveDemand());
            $this->assertContains($level->value, ['imitation', 'manipulation', 'precision', 'articulation', 'naturalization']);
        }
    }

    public function test_task_demand_is_not_automatic_learner_capability(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(BloomLevel::Apply, $state->bloom_demand);
        $this->assertSame(DaveLevel::Manipulation, $state->dave_demand);
        $this->assertNotSame($state->bloom_demand->value, $state->cognitive_indicator);
        $this->assertNotSame($state->dave_demand->value, $state->psychomotor_indicator);
        $this->assertSame('successful_task_outcome_observed', $state->cognitive_indicator);
        $this->assertSame('successful_execution_observed', $state->psychomotor_indicator);
    }

    public function test_validated_evidence_is_used_as_input(): void
    {
        $evidence = $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertTrue($state->validatedEvidence->contains('id', $evidence->id));
        $this->assertSame([$evidence->id], $state->fusion_summary['evidence_ids']);
    }

    public function test_evidence_fusion_uses_multiple_evidence_not_only_latest(): void
    {
        $first = $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $second = $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::Progressing, $state->state);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $state->validatedEvidence->pluck('id')->all());
        $this->assertGreaterThanOrEqual(2, $state->fusion_summary['usable_count']);
        $this->assertContains('submission_rejected', $state->fusion_summary['evidence_types']);
        $this->assertContains('submission_accepted', $state->fusion_summary['evidence_types']);
    }

    public function test_valid_evidence_can_support_inference(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::Stable, $state->state);
        $this->assertSame(1, $state->fusion_summary['valid_count']);
    }

    public function test_uncertain_evidence_is_not_treated_as_valid_support(): void
    {
        $this->seedEvidence('execution_runtime_failure', EvidenceCategory::SystemContext, EvidenceQuality::Uncertain, EvidenceConfidence::Low);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::InsufficientEvidence, $state->state);
        $this->assertSame(0, $state->fusion_summary['usable_count']);
        $this->assertSame(1, $state->fusion_summary['uncertain_count']);
        $this->assertStringContainsString('Uncertain evidence is not treated as valid support', $state->explanation);
    }

    public function test_context_dependent_evidence_influences_confidence(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);
        $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);
        $this->assertSame(2, $state->fusion_summary['context_dependent_count']);
        $this->assertContains($state->state_confidence, [StateConfidence::Low, StateConfidence::Medium]);
    }

    public function test_high_medium_low_evidence_confidence_are_recorded_in_fusion(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);
        $this->seedEvidence('code_submit', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('code_run', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Low);
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(2, $state->fusion_summary['high_confidence_count']);
        $this->assertSame(1, $state->fusion_summary['medium_confidence_count']);
        $this->assertSame(1, $state->fusion_summary['low_confidence_count']);
        $this->assertInstanceOf(StateConfidence::class, $state->state_confidence);
        $this->assertContains($state->state_confidence->value, ['high', 'medium', 'low']);
        $this->assertArrayHasKey('usable_count', $state->fusion_summary);
        $this->assertSame('progressing_corrective_success', $state->inference_rule);
    }

    public function test_cognitive_indicator_is_produced(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame('corrective_application_observed', $state->cognitive_indicator);
        $this->assertNotSame(BloomLevel::Apply->value, $state->cognitive_indicator);
    }

    public function test_psychomotor_indicator_is_produced(): void
    {
        $this->seedEvidence('code_run', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame('error_correction_then_successful_execution', $state->psychomotor_indicator);
        $this->assertNotSame(DaveLevel::Manipulation->value, $state->psychomotor_indicator);
    }

    public function test_observable_behavioral_indicators_are_produced(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);
        $this->seedEvidence('code_submit', EvidenceCategory::Interaction, EvidenceQuality::Valid, EvidenceConfidence::Medium);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertContains('persistent_attempt_behavior', $state->behavioral_indicators);
    }

    public function test_no_psychological_diagnosis(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertDoesNotMatchRegularExpression(
            '/\b(frustrated|confused|demotivated|anxious|depressed|resilient|motivated)\b/i',
            $state->explanation
        );
        $this->assertStringNotContainsStringIgnoringCase('Learner is struggling', $state->explanation);
        $this->assertStringContainsString('not diagnose', $state->explanation);
    }

    public function test_insufficient_evidence_when_no_validated_evidence(): void
    {
        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::InsufficientEvidence, $state->state);
        $this->assertSame('insufficient_evidence_minimal_usable', $state->inference_rule);
        $this->assertSame(StateConfidence::High, $state->state_confidence);
    }

    public function test_progressing_state(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::Progressing, $state->state);
        $this->assertSame('progressing_corrective_success', $state->inference_rule);
        $this->assertContains('corrective_behavior', $state->behavioral_indicators);
    }

    public function test_stable_state(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::Stable, $state->state);
        $this->assertSame('stable_successful_outcome', $state->inference_rule);
    }

    public function test_needs_support_state(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);
        $this->assertSame('needs_support_repeated_failures', $state->inference_rule);
        $this->assertStringContainsString('not an adaptive intervention', $state->explanation);
    }

    public function test_state_confidence_is_separate_from_evidence_confidence(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertInstanceOf(StateConfidence::class, $state->state_confidence);
        $this->assertSame(EvidenceConfidence::High, $state->validatedEvidence->first()->confidence);
        $this->assertArrayHasKey('high_confidence_count', $state->fusion_summary);
        $this->assertNotSame('evidence_confidence', $state->getAttributes()['state_confidence'] ?? null);
        $this->assertTrue(Schema::hasColumn('learning_states', 'state_confidence'));
        $this->assertFalse(Schema::hasColumn('learning_states', 'evidence_confidence'));
    }

    public function test_explanation_is_present(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertNotSame('', $state->explanation);
        $this->assertStringContainsString('Learning State: stable.', $state->explanation);
        $this->assertStringContainsString('Cognitive demand: apply.', $state->explanation);
        $this->assertStringContainsString('Psychomotor demand: manipulation.', $state->explanation);
    }

    public function test_provenance_links_to_validated_evidence(): void
    {
        $a = $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $b = $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $state->validatedEvidence->pluck('id')->all());
        $this->assertTrue($state->validatedEvidence->first()->learningEvent()->exists());
        $this->assertDatabaseHas('learning_state_evidence', [
            'learning_state_id' => $state->id,
            'validated_evidence_id' => $a->id,
        ]);
    }

    public function test_idempotent_inference_does_not_duplicate_state(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $first = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);
        $second = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, LearningState::query()->count());
        $this->assertSame($first->inference_key, $second->inference_key);
    }

    public function test_m3_event_semantics_unchanged(): void
    {
        $event = LearningEvent::record(
            'submission_accepted',
            $this->student->id,
            $this->course->id,
            $this->activity->id,
            ['status' => 'success', 'passes_evaluation' => true],
        );

        $this->assertSame('submission_accepted', $event->event_type);
        $this->assertDatabaseHas('learning_events', [
            'id' => $event->id,
            'event_type' => 'submission_accepted',
        ]);
    }

    public function test_m4_t02_validated_evidence_remains_intact(): void
    {
        $evidence = $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $fresh = $evidence->fresh();
        $this->assertSame(EvidenceCategory::Performance, $fresh->evidence_category);
        $this->assertSame(EvidenceQuality::Valid, $fresh->quality);
        $this->assertSame(EvidenceConfidence::High, $fresh->confidence);
        $this->assertNotNull($fresh->validation_reason);
    }

    public function test_no_intervention_or_recommendation_surface(): void
    {
        $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertArrayNotHasKey('intervention', $state->getAttributes());
        $this->assertArrayNotHasKey('recommendation', $state->getAttributes());
        $this->assertFalse(Schema::hasColumn('learning_states', 'intervention'));
        $this->assertFalse(Schema::hasColumn('learning_states', 'recommendation'));
        // M4-T03 inference alone must not create adaptive interventions.
        // AdaptiveInterventionService belongs to M4-T04 and is invoked explicitly.
        $this->assertSame(0, \App\Models\AdaptiveIntervention::query()->count());
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningRecommendationService'));
    }

    public function test_task_context_influences_inference_output(): void
    {
        $this->activity->update([
            'bloom_demand' => BloomLevel::Analyze,
            'dave_demand' => DaveLevel::Precision,
        ]);
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $state = $this->service->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(BloomLevel::Analyze, $state->bloom_demand);
        $this->assertSame(DaveLevel::Precision, $state->dave_demand);
        $this->assertStringContainsString('Cognitive demand: analyze.', $state->explanation);
        $this->assertStringContainsString('Psychomotor demand: precision.', $state->explanation);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function seedEvidence(
        string $evidenceType,
        EvidenceCategory $category,
        EvidenceQuality $quality,
        EvidenceConfidence $confidence,
        array $context = [],
    ): ValidatedEvidence {
        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => match ($evidenceType) {
                'repeated_submission_failures' => 'submission_rejected',
                'repeated_execution' => 'code_run',
                'execution_runtime_failure', 'execution_timeout', 'execution_system_anomaly' => 'code_run',
                default => $evidenceType,
            },
            'payload' => ['seeded' => true],
            'occurred_at' => now(),
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'source_record_type' => null,
            'source_record_id' => null,
            'evidence_category' => $category->value,
            'evidence_type' => $evidenceType,
            'observed_value' => ['summary' => $evidenceType],
            'context_summary' => array_merge([
                'task_repetition' => 'new',
                'task_difficulty' => 'medium',
                'execution_anomaly' => 'none',
                'network_environment' => 'unknown',
            ], $context),
            'quality' => $quality->value,
            'confidence' => $confidence->value,
            'validation_reason' => 'Seeded validated evidence for M4-T03 tests.',
            'validated_at' => now(),
        ]);
    }
}
