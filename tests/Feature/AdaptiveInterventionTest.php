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
use App\Enums\SocraticResponseType;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\AdaptiveInterventionService;
use App\Services\Research\LearningStateInferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdaptiveInterventionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private AdaptiveInterventionService $service;

    private LearningStateInferenceService $stateService;

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
        $this->service = app(AdaptiveInterventionService::class);
        $this->stateService = app(LearningStateInferenceService::class);
    }

    public function test_needs_support_produces_adaptive_intervention(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);

        $this->assertSame(LearningStateValue::NeedsSupport, $state->state);

        $intervention = $this->service->createForLearningState($state);

        $this->assertSame($state->id, $intervention->learning_state_id);
        $this->assertTrue($intervention->is_remedial);
        $this->assertTrue($intervention->is_strong);
        $this->assertNotSame('', $intervention->content);
        $this->assertNotSame('', $intervention->reason);
        $this->assertContains($intervention->intervention_type, [
            InterventionType::Hint,
            InterventionType::SocraticQuestion,
            InterventionType::ConceptExplanation,
            InterventionType::CorrectiveFeedback,
            InterventionType::GuidedRetry,
            InterventionType::WorkedExample,
        ]);
    }

    public function test_progressing_produces_reinforcement(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $this->assertSame(LearningStateValue::Progressing, $state->state);

        $intervention = $this->service->createForLearningState($state);

        $this->assertSame(InterventionType::Reinforcement, $intervention->intervention_type);
        $this->assertFalse($intervention->is_remedial);
        $this->assertFalse($intervention->is_strong);
        $this->assertStringContainsString('progressing', $intervention->reason);
    }

    public function test_stable_does_not_produce_remedial_intervention(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $this->assertSame(LearningStateValue::Stable, $state->state);

        $intervention = $this->service->createForLearningState($state);

        $this->assertSame(InterventionType::Reinforcement, $intervention->intervention_type);
        $this->assertFalse($intervention->is_remedial);
        $this->assertNotContains($intervention->intervention_type, [
            InterventionType::CorrectiveFeedback,
            InterventionType::GuidedRetry,
            InterventionType::WorkedExample,
        ]);
    }

    public function test_insufficient_evidence_does_not_produce_strong_intervention(): void
    {
        $state = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);
        $this->assertSame(LearningStateValue::InsufficientEvidence, $state->state);

        $intervention = $this->service->createForLearningState($state);

        $this->assertFalse($intervention->is_strong);
        $this->assertFalse($intervention->is_remedial);
        $this->assertSame('insufficient_evidence_no_strong_intervention', $intervention->selection_rule);
        $this->assertStringContainsString('not yet sufficient', $intervention->content);
        $this->assertNotContains($intervention->intervention_type, [
            InterventionType::CorrectiveFeedback,
            InterventionType::GuidedRetry,
            InterventionType::WorkedExample,
        ]);
    }

    public function test_cognitive_support_uses_socratic_or_concept_help(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_multiple_rejections',
            'unresolved_performance_outcome_observed',
            'execution_practice_with_unresolved_outcome',
            ['persistent_attempt_behavior'],
        );
        // Force cognitive path by clearing psychomotor unresolved and persistent flags.
        $state->psychomotor_indicator = 'task_skill_demand_context_only';
        $state->behavioral_indicators = [];
        $state->save();

        $intervention = $this->service->createForLearningState($state->fresh());

        $this->assertContains($intervention->intervention_type, [
            InterventionType::SocraticQuestion,
            InterventionType::ConceptExplanation,
            InterventionType::Hint,
        ]);
        $this->assertSame('cognitive', $intervention->metadata['support_focus']);
        $this->assertStringContainsString('Bloom demand', $intervention->reason);
        $this->assertStringNotContainsString('learner has reached Bloom', strtolower($intervention->reason));
    }

    public function test_psychomotor_support_uses_guided_retry(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_repeated_failures',
            'unresolved_performance_outcome_observed',
            'execution_practice_with_unresolved_outcome',
            [],
        );

        $intervention = $this->service->createForLearningState($state);

        $this->assertSame(InterventionType::GuidedRetry, $intervention->intervention_type);
        $this->assertSame('psychomotor', $intervention->metadata['support_focus']);
        $this->assertStringContainsString('Dave', $intervention->reason);
        $this->assertStringContainsString('without claiming the learner has reached that Dave level', $intervention->reason);
    }

    public function test_behavioral_indicators_remain_observable_without_psychological_diagnosis(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_repeated_failures',
            'unresolved_performance_outcome_observed',
            'task_skill_demand_context_only',
            ['persistent_attempt_behavior'],
        );

        $intervention = $this->service->createForLearningState($state);

        $this->assertContains('persistent_attempt_behavior', $intervention->metadata['behavioral_indicators']);
        $this->assertSame(InterventionType::Hint, $intervention->intervention_type);
        $this->assertDoesNotMatchRegularExpression(
            '/\b(frustrated|anxious|lazy|unmotivated|demotivated|depressed|malas|cemas)\b/i',
            $intervention->content.' '.$intervention->reason
        );
        $this->assertStringContainsString('no psychological diagnosis', strtolower($intervention->reason));
    }

    public function test_socratic_response_asks_question_and_does_not_give_direct_answer(): void
    {
        $state = $this->makeState(
            LearningStateValue::NeedsSupport,
            'needs_support_multiple_rejections',
            'unresolved_performance_outcome_observed',
            'task_skill_demand_context_only',
            [],
        );

        $intervention = $this->service->createForLearningState($state);

        $this->assertSame(InterventionType::SocraticQuestion, $intervention->intervention_type);
        $this->assertSame(SocraticResponseType::GuidedQuestion, $intervention->socratic_type);
        $this->assertStringContainsString('?', $intervention->content);
        $this->assertFalse($intervention->metadata['provides_direct_answer']);
        $this->assertDoesNotMatchRegularExpression('/\b(the correct answer is|jawaban yang benar adalah)\b/i', $intervention->content);
    }

    public function test_provenance_traces_intervention_to_state_evidence_and_event(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);

        $intervention = $this->service->createForLearningState($state);

        $this->assertTrue($intervention->learningState->is($state));
        $this->assertNotEmpty($intervention->metadata['validated_evidence_ids']);
        $this->assertEqualsCanonicalizing(
            $state->validatedEvidence->pluck('id')->all(),
            $intervention->metadata['validated_evidence_ids']
        );

        $evidence = $state->validatedEvidence->first();
        $this->assertNotNull($evidence);
        $this->assertTrue($evidence->learningEvent()->exists());
        $this->assertInstanceOf(LearningEvent::class, $evidence->learningEvent);
    }

    public function test_idempotent_intervention_for_same_learning_state(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $first = $this->service->createForLearningState($state);
        $second = $this->service->createForLearningState($state);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AdaptiveIntervention::query()->where('learning_state_id', $state->id)->count());
        $this->assertSame($first->intervention_key, $second->intervention_key);
    }

    public function test_no_learning_recommendation_or_longitudinal_surface(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);

        $intervention = $this->service->createForLearningState($state);

        $this->assertNull($intervention->metadata['recommendation']);
        $this->assertFalse($intervention->metadata['longitudinal_analysis']);
        $this->assertFalse(Schema::hasColumn('adaptive_interventions', 'recommendation'));
        $this->assertFalse(Schema::hasColumn('adaptive_interventions', 'trajectory_score'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LearningRecommendationService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\LongitudinalAnalyticsService'));
    }

    public function test_t03_learning_state_inference_unchanged_by_intervention_creation(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);

        $before = $state->only([
            'state',
            'state_confidence',
            'cognitive_indicator',
            'psychomotor_indicator',
            'inference_rule',
            'explanation',
        ]);

        $this->service->createForLearningState($state);

        $after = $state->fresh()->only(array_keys($before));
        $this->assertSame($before, $after);
        $this->assertSame(LearningStateValue::Progressing, $state->fresh()->state);
    }

    public function test_t02_validated_evidence_untouched(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High],
        ]);
        $evidence = $state->validatedEvidence->first();
        $snapshot = $evidence->only([
            'evidence_category',
            'evidence_type',
            'quality',
            'confidence',
            'validation_reason',
        ]);

        $this->service->createForLearningState($state);

        $this->assertSame($snapshot, $evidence->fresh()->only(array_keys($snapshot)));
    }

    public function test_all_intervention_types_are_defined(): void
    {
        $values = array_map(fn (InterventionType $type) => $type->value, InterventionType::cases());
        $this->assertEqualsCanonicalizing([
            'hint',
            'socratic_question',
            'concept_explanation',
            'worked_example',
            'corrective_feedback',
            'guided_retry',
            'reinforcement',
        ], $values);
    }

    public function test_all_socratic_response_types_are_defined(): void
    {
        $values = array_map(fn (SocraticResponseType $type) => $type->value, SocraticResponseType::cases());
        $this->assertEqualsCanonicalizing([
            'clarifying_question',
            'concept_check',
            'guided_question',
            'reflection_question',
            'next_step_hint',
        ], $values);
    }

    public function test_learner_retry_flow_can_continue_after_intervention(): void
    {
        $state = $this->stateFromEvidence([
            ['submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium],
            ['repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium],
        ]);

        $intervention = $this->service->createForLearningState($state);
        $this->assertTrue($intervention->is_remedial);

        // Simulate retry producing new evidence and a new learning state without longitudinal analysis.
        $this->seedEvidence('submission_accepted', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High);
        $nextState = $this->stateService->inferForLearnerActivity($this->student->id, $this->activity->id);

        $this->assertNotSame($state->inference_key, $nextState->inference_key);
        $this->assertSame(LearningStateValue::Progressing, $nextState->state);
        $this->assertFalse($intervention->fresh()->metadata['longitudinal_analysis']);
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
            'inference_key' => hash('sha256', 't04-test-'.$value->value.'-'.$rule.'-'.$evidence->id),
            'state' => $value->value,
            'state_confidence' => StateConfidence::Medium->value,
            'bloom_demand' => BloomLevel::Apply->value,
            'dave_demand' => DaveLevel::Manipulation->value,
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => $psychomotor,
            'behavioral_indicators' => $behavioral,
            'fusion_summary' => [
                'usable_count' => 1,
                'evidence_ids' => [$evidence->id],
            ],
            'explanation' => 'Seeded learning state for M4-T04 tests.',
            'inference_rule' => $rule,
            'inferred_at' => now(),
        ]);

        $state->validatedEvidence()->sync([$evidence->id]);

        return $state->fresh(['validatedEvidence', 'activity']);
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
                'repeated_execution' => 'code_run',
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
            'validation_reason' => 'Seeded validated evidence for M4-T04 tests.',
            'validated_at' => now(),
        ]);
    }
}
