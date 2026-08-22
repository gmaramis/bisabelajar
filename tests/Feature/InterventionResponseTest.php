<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionResponseClassification;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;
use App\Enums\ObservedImprovementSignal;
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
use App\Services\Research\InterventionResponseQuery;
use App\Services\Research\ResearchEvidenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InterventionResponseTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private InterventionResponseQuery $query;

    private ResearchEvidenceQuery $research;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($this->course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $this->activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $this->query = app(InterventionResponseQuery::class);
        $this->research = app(ResearchEvidenceQuery::class);
    }

    public function test_needs_support_to_progressing_is_positive_observed_improvement(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(
            InterventionResponseClassification::PositiveResponse->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertSame(
            ObservedImprovementSignal::ObservedImprovement->value,
            $result['research_interpretation']['observed_improvement_signal'],
        );
        $this->assertTrue($result['research_interpretation']['observed_improvement']);
        $this->assertFalse($result['research_interpretation']['claims_intervention_caused_improvement']);
        $this->assertStringContainsString('not proof', strtolower($result['research_interpretation']['explanation']));
    }

    public function test_needs_support_to_stable_is_positive_observed_improvement(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Stable,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(InterventionResponseClassification::PositiveResponse->value, $result['research_interpretation']['response_classification']);
        $this->assertSame(ObservedImprovementSignal::ObservedImprovement->value, $result['research_interpretation']['observed_improvement_signal']);
    }

    public function test_progressing_to_stable_is_stabilization_signal(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::Progressing,
            after: LearningStateValue::Stable,
            postEvidenceType: 'submission_accepted',
            remedial: false,
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(InterventionResponseClassification::PositiveResponse->value, $result['research_interpretation']['response_classification']);
        $this->assertSame(ObservedImprovementSignal::StabilizationSignal->value, $result['research_interpretation']['observed_improvement_signal']);
    }

    public function test_needs_support_to_needs_support_is_persistent_difficulty(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::NeedsSupport,
            postEvidenceType: 'submission_rejected',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(
            InterventionResponseClassification::NegativeOrPersistentDifficulty->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertSame(
            ObservedImprovementSignal::NoObservedImprovement->value,
            $result['research_interpretation']['observed_improvement_signal'],
        );
        $this->assertFalse($result['research_interpretation']['observed_improvement']);
    }

    public function test_stable_to_needs_support_is_deterioration_signal(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::Stable,
            after: LearningStateValue::NeedsSupport,
            postEvidenceType: 'submission_rejected',
            remedial: false,
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(
            InterventionResponseClassification::NegativeOrPersistentDifficulty->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertSame(
            ObservedImprovementSignal::DeteriorationSignal->value,
            $result['research_interpretation']['observed_improvement_signal'],
        );
    }

    public function test_no_post_evidence_is_insufficient_evidence(): void
    {
        $before = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(30), withEvidence: true);
        $intervention = $this->makeIntervention($before, now()->subMinutes(20));

        $result = $this->query->forIntervention($intervention->fresh(['learningState', 'activity']));

        $this->assertSame(
            InterventionResponseClassification::InsufficientEvidence->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertSame(
            ObservedImprovementSignal::Inconclusive->value,
            $result['research_interpretation']['observed_improvement_signal'],
        );
    }

    public function test_partial_response_when_positive_transition_but_unresolved_indicator_remains(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
            afterCognitive: 'unresolved_performance_outcome_observed',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(
            InterventionResponseClassification::PartialResponse->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertTrue($result['research_interpretation']['observed_improvement']);
    }

    public function test_intervention_context_separated_from_observed_outcome(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertArrayHasKey('intervention_context', $result);
        $this->assertArrayHasKey('observed_outcome', $result);
        $this->assertArrayHasKey('research_interpretation', $result);
        $this->assertSame($intervention->id, $result['intervention_context']['adaptive_intervention_id']);
        $this->assertSame(LearningStateValue::NeedsSupport->value, $result['observed_outcome']['before_state']);
        $this->assertSame(LearningStateValue::Progressing->value, $result['observed_outcome']['after_state']);
        $this->assertSame('guided_retry', $result['intervention_context']['intervention_type']);
    }

    public function test_provenance_and_research_learner_id(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame(
            $this->research->researchLearnerId($this->student->id),
            $result['research_learner_id'],
        );
        $this->assertNotEmpty($result['provenance']['pre_validated_evidence_ids']);
        $this->assertNotEmpty($result['provenance']['post_validated_evidence_ids']);
        $this->assertNotEmpty($result['provenance']['post_learning_event_ids']);
    }

    public function test_same_concept_learning_area_preserved(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertSame('concept:loops', $result['learning_area']['key']);
        $this->assertSame('activity_concept', $result['learning_area']['representation']);
        $this->assertSame('task_demand', $result['learning_area']['bloom_semantics']);
        $this->assertSame(BloomLevel::Apply->value, $result['learning_area']['bloom_demand']);
    }

    public function test_linked_retry_outcome_included_in_observed_outcome(): void
    {
        [$intervention, $before, $after] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        NextLearningAction::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_state_id' => $after->id,
            'adaptive_intervention_id' => $intervention->id,
            'action' => NextLearningActionType::Continue,
            'retry_outcome' => 'success',
            'decision_key' => hash('sha256', 'm5-05-retry-success'),
            'decided_at' => now()->subMinutes(5),
        ]);

        $result = $this->query->forIntervention($intervention->fresh());

        $this->assertContains('success', $result['observed_outcome']['retry_outcomes']);
        $this->assertNotEmpty($result['observed_outcome']['linked_next_learning_action_ids']);
        unset($before);
    }

    public function test_no_causal_claims_and_no_m5_06_07(): void
    {
        [$intervention] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $result = $this->query->forIntervention($intervention);

        $this->assertFalse($result['analysis_boundary']['claims_causal_effectiveness']);
        $this->assertFalse($result['analysis_boundary']['claims_treatment_effect']);
        $this->assertFalse($result['analysis_boundary']['claims_intervention_caused_improvement']);
        $this->assertFalse($result['analysis_boundary']['claims_statistical_significance']);
        $this->assertFalse($result['analysis_boundary']['performs_contextual_variation_analysis']);
        $this->assertFalse($result['analysis_boundary']['performs_research_export']);
        $this->assertFalse(class_exists('App\\Services\\Research\\ContextualVariationService'));
        $this->assertFalse(Schema::hasTable('intervention_responses'));
        $this->assertFalse(Schema::hasTable('intervention_effectiveness'));
    }

    public function test_does_not_mutate_m4_records(): void
    {
        [$intervention, $before, $after] = $this->seedInterventionPair(
            before: LearningStateValue::NeedsSupport,
            after: LearningStateValue::Progressing,
            postEvidenceType: 'submission_accepted',
        );

        $beforeSnap = $before->fresh()->only(['state', 'inference_key', 'explanation']);
        $afterSnap = $after->fresh()->only(['state', 'inference_key', 'explanation']);
        $interventionSnap = $intervention->fresh()->only(['intervention_type', 'selection_rule', 'content']);
        $counts = [
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
        ];

        $this->query->forIntervention($intervention);
        $this->query->forLearnerActivity($this->student->id, $this->activity->id);

        $this->assertSame($beforeSnap, $before->fresh()->only(array_keys($beforeSnap)));
        $this->assertSame($afterSnap, $after->fresh()->only(array_keys($afterSnap)));
        $this->assertSame($interventionSnap, $intervention->fresh()->only(array_keys($interventionSnap)));
        $this->assertSame($counts['states'], LearningState::query()->count());
        $this->assertSame($counts['interventions'], AdaptiveIntervention::query()->count());
        $this->assertSame($counts['evidence'], ValidatedEvidence::query()->count());
    }

    public function test_uncertain_system_evidence_not_treated_as_strong_post_response(): void
    {
        $before = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(30), withEvidence: true);
        $intervention = $this->makeIntervention($before, now()->subMinutes(20));

        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => 'code_run',
            'payload' => [],
            'occurred_at' => now()->subMinutes(10),
        ]);
        ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::SystemContext->value,
            'evidence_type' => 'execution_timeout',
            'observed_value' => ['summary' => 'timeout'],
            'context_summary' => [],
            'quality' => EvidenceQuality::Uncertain->value,
            'confidence' => EvidenceConfidence::Low->value,
            'validation_reason' => 'system',
            'validated_at' => now()->subMinutes(10),
        ]);

        $result = $this->query->forIntervention($intervention->fresh(['learningState', 'activity']));

        $this->assertSame(
            InterventionResponseClassification::InsufficientEvidence->value,
            $result['research_interpretation']['response_classification'],
        );
        $this->assertSame([], $result['observed_outcome']['post_evidence_ids']);
    }

    /**
     * @return array{0: AdaptiveIntervention, 1: LearningState, 2: LearningState}
     */
    private function seedInterventionPair(
        LearningStateValue $before,
        LearningStateValue $after,
        string $postEvidenceType,
        bool $remedial = true,
        ?string $afterCognitive = 'successful_task_outcome_observed',
    ): array {
        $beforeState = $this->makeState($before, now()->subMinutes(40), withEvidence: true, cognitive: 'unresolved_performance_outcome_observed');
        $intervention = $this->makeIntervention($beforeState, now()->subMinutes(30), $remedial);

        $afterState = $this->makeState(
            $after,
            now()->subMinutes(10),
            withEvidence: true,
            evidenceType: $postEvidenceType,
            cognitive: $afterCognitive,
        );

        return [$intervention->fresh(['learningState', 'activity', 'nextLearningActions']), $beforeState, $afterState];
    }

    private function makeState(
        LearningStateValue $state,
        $inferredAt,
        bool $withEvidence = false,
        string $evidenceType = 'submission_rejected',
        ?string $cognitive = null,
    ): LearningState {
        $record = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $inferredAt,
            'inference_key' => hash('sha256', uniqid($state->value, true)),
            'cognitive_indicator' => $cognitive,
            'psychomotor_indicator' => null,
            'behavioral_indicators' => ['persistent_attempt_behavior'],
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Fixture state for M5-05.',
            'inference_rule' => 'fixture_m5_05',
        ]);

        if ($withEvidence) {
            $evidence = $this->makeEvidence($evidenceType, $inferredAt);
            $record->validatedEvidence()->sync([$evidence->id]);
        }

        return $record->fresh(['validatedEvidence.learningEvent']);
    }

    private function makeIntervention(LearningState $state, $createdAt, bool $remedial = true): AdaptiveIntervention
    {
        $evidenceIds = $state->validatedEvidence->pluck('id')->values()->all();

        $intervention = AdaptiveIntervention::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_state_id' => $state->id,
            'intervention_type' => InterventionType::GuidedRetry,
            'is_remedial' => $remedial,
            'is_strong' => $remedial,
            'selection_rule' => 'fixture_m5_05',
            'reason' => 'Fixture intervention for M5-05.',
            'content' => 'Try again with guidance.',
            'intervention_key' => hash('sha256', uniqid('intervention', true)),
            'metadata' => [
                'validated_evidence_ids' => $evidenceIds,
            ],
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        // Ensure created_at is exactly the intended cut time.
        $intervention->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $intervention->fresh();
    }

    private function makeEvidence(string $type, $at): ValidatedEvidence
    {
        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => $type,
            'payload' => ['seeded' => true],
            'occurred_at' => $at,
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::Performance->value,
            'evidence_type' => $type,
            'observed_value' => ['summary' => $type],
            'context_summary' => [],
            'quality' => EvidenceQuality::Valid->value,
            'confidence' => EvidenceConfidence::High->value,
            'validation_reason' => 'Fixture M5-05',
            'validated_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
