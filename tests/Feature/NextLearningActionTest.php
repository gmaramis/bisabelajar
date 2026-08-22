<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\NextLearningAction;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NextLearningActionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NextLearningActionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private NextLearningActionService $service;

    private LearningStateInferenceService $stateService;

    private AdaptiveInterventionService $interventionService;

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
        $this->service = app(NextLearningActionService::class);
        $this->stateService = app(LearningStateInferenceService::class);
        $this->interventionService = app(AdaptiveInterventionService::class);
    }

    public function test_insufficient_evidence_collects_more_evidence(): void
    {
        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::InsufficientEvidence, $state->state);

        $decision = $this->service->decideForLearningState($state);

        $this->assertSame(NextLearningActionType::CollectMoreEvidence, $decision->action);
        $this->assertSame('insufficient_evidence → collect_more_evidence', $decision->decision_rule);
        $this->assertStringContainsString('insufficient_evidence', $decision->reason);
    }

    public function test_progressing_continues(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);
        $this->assertSame(LearningStateValue::Progressing, $state->state);

        $decision = $this->service->decideForLearningState($state);

        $this->assertSame(NextLearningActionType::Continue, $decision->action);
        $this->assertSame('progressing + improvement → continue', $decision->decision_rule);
    }

    public function test_stable_continues(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);
        $this->assertSame(LearningStateValue::Stable, $state->state);

        $decision = $this->service->decideForLearningState($state);

        $this->assertSame(NextLearningActionType::Continue, $decision->action);
        $this->assertFalse(str_contains($decision->decision_rule, 'remedial'));
        $this->assertSame('stable + no new failure pattern → continue', $decision->decision_rule);
    }

    public function test_needs_support_with_successful_retry_continues(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_repeated_failures',
            'unresolved_performance_outcome_observed',
            'execution_practice_with_unresolved_outcome',
            ['persistent_attempt_behavior'],
        );
        $intervention = $this->interventionService->createForLearningState($state);

        $decision = $this->service->decideForLearningState($state, $intervention, 'success');

        $this->assertSame(NextLearningActionType::Continue, $decision->action);
        $this->assertSame('needs_support + intervention + successful retry → continue', $decision->decision_rule);
        $this->assertSame('success', $decision->retry_outcome);
        $this->assertSame($intervention->id, $decision->adaptive_intervention_id);
    }

    public function test_needs_support_with_failed_retry_practices_or_reviews(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_multiple_rejections',
            'unresolved_performance_outcome_observed',
            'task_skill_demand_context_only',
            [],
        );
        $intervention = $this->interventionService->createForLearningState($state);

        $decision = $this->service->decideForLearningState($state, $intervention, 'failure');

        $this->assertContains($decision->action, [
            NextLearningActionType::ReviewConcept,
            NextLearningActionType::PracticeAgain,
            NextLearningActionType::GuidedRetry,
            NextLearningActionType::Reassessment,
        ]);
        $this->assertSame('failure', $decision->retry_outcome);
    }

    public function test_cognitive_weakness_with_failed_retry_reviews_concept(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_multiple_rejections',
            'unresolved_performance_outcome_observed',
            'task_skill_demand_context_only',
            [],
        );
        $intervention = $this->makeIntervention($state, InterventionType::SocraticQuestion);

        $decision = $this->service->decideForLearningState($state, $intervention, 'failure');

        $this->assertSame(NextLearningActionType::ReviewConcept, $decision->action);
        $this->assertSame('needs_support + cognitive unresolved + retry failure → review_concept', $decision->decision_rule);
        $this->assertStringContainsString('Bloom demand', $decision->reason);
        $this->assertStringContainsString('task demand', strtolower($decision->reason));
    }

    public function test_psychomotor_weakness_with_failed_retry_guides_practice(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_repeated_failures',
            'successful_task_outcome_observed',
            'execution_practice_with_unresolved_outcome',
            [],
        );
        // Avoid persistent reassessment path by using a single rejection evidence only.
        $intervention = $this->makeIntervention($state, InterventionType::GuidedRetry);

        $decision = $this->service->decideForLearningState($state, $intervention, 'failure');

        $this->assertContains($decision->action, [
            NextLearningActionType::GuidedRetry,
            NextLearningActionType::PracticeAgain,
        ]);
        $this->assertStringContainsString('psychomotor', $decision->decision_rule);
        $this->assertStringContainsString('Dave demand', $decision->reason);
    }

    public function test_persistent_weak_area_requests_reassessment_without_generating_question(): void
    {
        $first = $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $second = $this->seedEvidence('repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium);

        $state = LearningState::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'inference_key' => hash('sha256', 't05-persistent-'.$first->id.'-'.$second->id),
            'state' => LearningStateValue::NeedsSupport->value,
            'state_confidence' => StateConfidence::Medium->value,
            'bloom_demand' => BloomLevel::Apply->value,
            'dave_demand' => DaveLevel::Manipulation->value,
            'cognitive_indicator' => 'unresolved_performance_outcome_observed',
            'psychomotor_indicator' => 'task_skill_demand_context_only',
            'behavioral_indicators' => ['persistent_attempt_behavior'],
            'fusion_summary' => ['evidence_ids' => [$first->id, $second->id]],
            'explanation' => 'Seeded persistent weak-area state.',
            'inference_rule' => 'needs_support_repeated_failures',
            'inferred_at' => now(),
        ]);
        $state->validatedEvidence()->sync([$first->id, $second->id]);
        $state = $state->fresh(['validatedEvidence', 'activity']);

        $intervention = $this->makeIntervention($state, InterventionType::CorrectiveFeedback, [$first->id, $second->id]);
        $decision = $this->service->decideForLearningState($state, $intervention, 'failure');

        $this->assertSame(NextLearningActionType::Reassessment, $decision->action);
        $this->assertFalse($decision->metadata['creates_reassessment_question']);
        $this->assertStringContainsString('No reassessment question is generated by T05', $decision->reason);
        $this->assertFalse(Schema::hasColumn('next_learning_actions', 'reassessment_question'));
        $this->assertFalse(class_exists('App\\Services\\Research\\ReassessmentQuestionGenerator'));
    }

    public function test_explanation_and_decision_rule_are_present(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $decision = $this->service->decideForLearningState($state);

        $this->assertNotSame('', $decision->reason);
        $this->assertNotSame('', $decision->decision_rule);
        $this->assertStringContainsString('→', $decision->decision_rule);
    }

    public function test_provenance_traces_to_state_evidence_event_and_intervention(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);
        $intervention = $this->interventionService->createForLearningState($state);

        $decision = $this->service->decideForLearningState($state, $intervention, 'failure');

        $this->assertTrue($decision->learningState->is($state));
        $this->assertTrue($decision->adaptiveIntervention->is($intervention));
        $this->assertEqualsCanonicalizing(
            $state->validatedEvidence->pluck('id')->all(),
            $decision->metadata['validated_evidence_ids']
        );
        $this->assertTrue($state->validatedEvidence->first()->learningEvent()->exists());
    }

    public function test_idempotent_decision_for_same_inputs(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $first = $this->service->decideForLearningState($state);
        $second = $this->service->decideForLearningState($state);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, NextLearningAction::query()->where('learning_state_id', $state->id)->count());
        $this->assertSame($first->decision_key, $second->decision_key);
    }

    public function test_t05_does_not_create_t04_intervention(): void
    {
        $before = AdaptiveIntervention::query()->count();
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $decision = $this->service->decideForLearningState($state);

        $this->assertSame($before, AdaptiveIntervention::query()->count());
        $this->assertFalse($decision->metadata['creates_intervention']);
    }

    public function test_t03_inference_does_not_auto_create_next_learning_action(): void
    {
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);

        $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame(0, NextLearningAction::query()->count());
    }

    public function test_t04_intervention_does_not_auto_create_next_learning_action(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);

        $this->interventionService->createForLearningState($state);

        $this->assertSame(0, NextLearningAction::query()->count());
    }

    public function test_no_ml_llm_or_longitudinal_surface(): void
    {
        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $decision = $this->service->decideForLearningState($state);

        $this->assertFalse($decision->metadata['ml_decision']);
        $this->assertFalse($decision->metadata['llm_decision']);
        $this->assertFalse($decision->metadata['longitudinal_analysis']);
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningRecommendationService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LongitudinalAnalyticsService'));
        $this->assertFalse(Schema::hasColumn('next_learning_actions', 'trajectory_score'));
    }

    public function test_all_next_learning_action_types_are_defined(): void
    {
        $values = array_map(fn (NextLearningActionType $type) => $type->value, NextLearningActionType::cases());
        $this->assertEqualsCanonicalizing([
            'continue',
            'review_concept',
            'practice_again',
            'guided_retry',
            'reassessment',
            'collect_more_evidence',
        ], $values);
    }

    public function test_derived_retry_success_from_new_evidence_after_intervention(): void
    {
        $reject = $this->seedEvidence('submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium);
        $state = LearningState::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'inference_key' => hash('sha256', 't05-derived-success-'.$reject->id),
            'state' => LearningStateValue::NeedsSupport->value,
            'state_confidence' => StateConfidence::Medium->value,
            'bloom_demand' => BloomLevel::Apply->value,
            'dave_demand' => DaveLevel::Manipulation->value,
            'cognitive_indicator' => 'unresolved_performance_outcome_observed',
            'psychomotor_indicator' => 'task_skill_demand_context_only',
            'behavioral_indicators' => [],
            'fusion_summary' => ['evidence_ids' => [$reject->id]],
            'explanation' => 'Seeded needs_support before retry.',
            'inference_rule' => 'needs_support_multiple_rejections',
            'inferred_at' => now(),
        ]);
        $state->validatedEvidence()->sync([$reject->id]);
        $state = $state->fresh(['validatedEvidence', 'activity']);

        $intervention = $this->makeIntervention($state, InterventionType::Hint, [$reject->id]);

        $accept = $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);
        $state->validatedEvidence()->sync([$reject->id, $accept->id]);
        $state = $state->fresh(['validatedEvidence', 'activity']);

        $decision = $this->service->decideForLearningState($state, $intervention);

        $this->assertSame('success', $decision->retry_outcome);
        $this->assertSame(NextLearningActionType::Continue, $decision->action);
    }

    /**
     * @param  list<array{0: string, 1: EvidenceCategory, 2: EvidenceQuality, 3: EvidenceConfidence}>  $rows
     */
    private function stateFromEvidence(array $rows): LearningState
    {
        foreach ($rows as [$type, $category, $quality, $confidence]) {
            $this->seedEvidence($type, $category, $quality, $confidence);
        }

        return $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
    }

    private function makeState(
        LearningStateValue $value,
        string $rule,
        ?string $cognitive,
        ?string $psychomotor,
        array $behavioral,
    ): LearningState {
        $evidence = $this->seedEvidence(
            'submission_rejected',
            EvidenceCategory::Performance,
            EvidenceQuality::Valid,
            EvidenceConfidence::Medium,
        );

        $state = LearningState::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'inference_key' => hash('sha256', 't05-state-'.$value->value.'-'.$rule.'-'.$evidence->id),
            'state' => $value->value,
            'state_confidence' => StateConfidence::Medium->value,
            'bloom_demand' => BloomLevel::Apply->value,
            'dave_demand' => DaveLevel::Manipulation->value,
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => $psychomotor,
            'behavioral_indicators' => $behavioral,
            'fusion_summary' => ['evidence_ids' => [$evidence->id]],
            'explanation' => 'Seeded learning state for M4-T05 tests.',
            'inference_rule' => $rule,
            'inferred_at' => now(),
        ]);
        $state->validatedEvidence()->sync([$evidence->id]);

        return $state->fresh(['validatedEvidence', 'activity']);
    }

    /**
     * @param  list<int>  $evidenceIds
     */
    private function makeIntervention(
        LearningState $state,
        InterventionType $type,
        array $evidenceIds = [],
    ): AdaptiveIntervention {
        $ids = $evidenceIds !== []
            ? $evidenceIds
            : $state->validatedEvidence->pluck('id')->all();

        return AdaptiveIntervention::query()->create([
            'user_id' => $state->user_id,
            'activity_id' => $state->activity_id,
            'learning_state_id' => $state->id,
            'intervention_key' => hash('sha256', 't05-intervention-'.$state->id.'-'.$type->value),
            'intervention_type' => $type->value,
            'socratic_type' => null,
            'target_state' => $state->state->value,
            'content' => 'Seeded intervention for T05.',
            'reason' => 'Seeded intervention reason.',
            'selection_rule' => 'seeded_t05_intervention',
            'is_strong' => true,
            'is_remedial' => true,
            'metadata' => [
                'validated_evidence_ids' => $ids,
            ],
        ]);
    }

    private function seedEvidence(
        string $evidenceType,
        EvidenceCategory $category,
        EvidenceQuality $quality,
        EvidenceConfidence $confidence,
    ): ValidatedEvidence {
        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => match ($evidenceType) {
                'repeated_submission_failures' => 'submission_rejected',
                default => $evidenceType,
            },
            'payload' => ['seeded' => true],
            'occurred_at' => now(),
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'evidence_category' => $category->value,
            'evidence_type' => $evidenceType,
            'observed_value' => ['summary' => $evidenceType],
            'context_summary' => [
                'task_repetition' => 'new',
                'task_difficulty' => 'medium',
                'execution_anomaly' => 'none',
                'network_environment' => 'unknown',
            ],
            'quality' => $quality->value,
            'confidence' => $confidence->value,
            'validation_reason' => 'Seeded validated evidence for M4-T05 tests.',
            'validated_at' => now(),
        ]);
    }
}
