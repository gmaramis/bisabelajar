<?php

namespace Tests\Feature;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\StateConfidence;
use App\Enums\WeakAreaClassification;
use App\Exceptions\ReassessmentGenerationException;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ReassessmentCandidate;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use App\Services\Research\Reassessment\ReassessmentCandidateValidator;
use App\Services\Research\Reassessment\ReassessmentSpecificationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiAssistedReassessmentTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Module $module;

    private LearningUnit $unit;

    private Activity $activity;

    private AiAssistedReassessmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create([
            'name' => 'Secret Learner',
            'email' => 'secret.learner@example.com',
        ]);
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $this->module = Module::factory()->for($this->course)->published()->create();
        $this->unit = LearningUnit::factory()->for($this->module)->published()->create();
        $this->activity = Activity::factory()->for($this->unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'title' => 'Original Loop Drill',
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
        ]);

        $this->app->bind(ReassessmentCandidateGenerator::class, DeterministicReassessmentCandidateGenerator::class);
        $this->service = app(AiAssistedReassessmentService::class);
    }

    public function test_weak_persistent_is_eligible_and_validated(): void
    {
        $this->seedPersistentWeakArea();

        $result = $this->service->createCandidateForLearningArea(
            $this->student->id,
            $this->course->id,
            'concept:loops',
        );

        $this->assertTrue($result['eligible']);
        $this->assertSame(ReassessmentCandidateStatus::Validated->value, $result['status']);
        $this->assertNotNull($result['candidate']);
        $this->assertSame('loops', $result['specification']['concept']);
        $this->assertSame('apply', $result['specification']['bloom_demand']);
        $this->assertSame('manipulation', $result['specification']['dave_demand']);
        $this->assertSame('task_demand', $result['specification']['bloom_semantics']);
        $this->assertDatabaseHas('reassessment_candidates', [
            'user_id' => $this->student->id,
            'status' => ReassessmentCandidateStatus::Validated->value,
        ]);
    }

    public function test_weak_repeated_failure_is_eligible(): void
    {
        $finding = $this->syntheticFinding(WeakAreaClassification::WeakRepeatedFailure);
        $result = $this->service->createCandidateFromFinding($finding);

        $this->assertTrue($result['eligible']);
        $this->assertContains($result['status'], [
            ReassessmentCandidateStatus::Validated->value,
            ReassessmentCandidateStatus::ValidationFailed->value,
            ReassessmentCandidateStatus::Generated->value,
        ]);
        $this->assertTrue($this->service->isEligible($finding));
    }

    public function test_weak_unresolved_is_eligible(): void
    {
        $finding = $this->syntheticFinding(WeakAreaClassification::WeakUnresolved);
        $this->assertTrue($this->service->isEligible($finding));

        $result = $this->service->createCandidateFromFinding($finding);
        $this->assertTrue($result['eligible']);
        $this->assertNotSame(ReassessmentCandidateStatus::NotEligibleInsufficientEvidence->value, $result['status']);
        $this->assertNotSame(ReassessmentCandidateStatus::NotEligibleRecovered->value, $result['status']);
    }

    public function test_insufficient_evidence_not_eligible(): void
    {
        $finding = $this->syntheticFinding(WeakAreaClassification::InsufficientEvidence);
        $result = $this->service->createCandidateFromFinding($finding);

        $this->assertFalse($result['eligible']);
        $this->assertSame(ReassessmentCandidateStatus::NotEligibleInsufficientEvidence->value, $result['status']);
        $this->assertNull($result['candidate']);
        $this->assertSame(0, ReassessmentCandidate::query()->count());
    }

    public function test_no_current_weakness_not_eligible_recovered(): void
    {
        $finding = $this->syntheticFinding(WeakAreaClassification::NoCurrentWeakness);
        $result = $this->service->createCandidateFromFinding($finding);

        $this->assertFalse($result['eligible']);
        $this->assertSame(ReassessmentCandidateStatus::NotEligibleRecovered->value, $result['status']);
        $this->assertNull($result['candidate']);
    }

    public function test_specification_preserves_concept_bloom_dave_as_task_demand(): void
    {
        $this->seedPersistentWeakArea();
        $result = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');
        $spec = $result['specification'];

        $this->assertSame('loops', $spec['concept']);
        $this->assertSame('Write a loop that iterates a list.', $spec['learning_objective']);
        $this->assertSame('apply', $spec['bloom_demand']);
        $this->assertSame('manipulation', $spec['dave_demand']);
        $this->assertSame('task_demand', $spec['bloom_semantics']);
        $this->assertSame('task_demand', $spec['dave_semantics']);
        $this->assertSame('not_inferred', $spec['learner_capability_semantics']);
        $this->assertArrayNotHasKey('learner_bloom_level', $spec);
        $this->assertArrayNotHasKey('learner_dave_level', $spec);
    }

    public function test_ai_safe_payload_excludes_pii(): void
    {
        $this->seedPersistentWeakArea();
        $result = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');
        $payload = $result['ai_safe_payload'];

        $this->assertArrayHasKey('research_learner_id', $payload);
        $this->assertArrayNotHasKey('learner_id', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $encoded = json_encode($payload);
        $this->assertStringNotContainsString('secret.learner@example.com', $encoded);
        $this->assertStringNotContainsString('Secret Learner', $encoded);
    }

    public function test_valid_candidate_accepted_via_abstraction(): void
    {
        $this->bindGenerator(fn (array $specification): array => $this->validCandidateFromSpec($specification));

        $finding = $this->syntheticFinding(WeakAreaClassification::WeakPersistent);
        $result = app(AiAssistedReassessmentService::class)->createCandidateFromFinding($finding);

        $this->assertSame(ReassessmentCandidateStatus::Validated->value, $result['status']);
        $this->assertTrue($result['validation_result']['valid']);
    }

    public function test_malformed_candidate_rejected(): void
    {
        $this->bindGenerator(fn (): array => [
            'title' => '',
            'task_prompt' => '',
            'scenario' => '',
            'concept' => 'loops',
            'learning_objective' => null,
            'bloom_demand' => 'apply',
            'dave_demand' => 'manipulation',
            'task_format' => 'coding_exercise',
            'expected_outcome' => '',
            'rubric' => '',
            'includes_direct_answer' => false,
            'generator_identity' => 'fake',
            'generator_model' => 'fake',
            'metadata' => [],
        ]);

        $result = app(AiAssistedReassessmentService::class)
            ->createCandidateFromFinding($this->syntheticFinding(WeakAreaClassification::WeakPersistent));

        $this->assertSame(ReassessmentCandidateStatus::ValidationFailed->value, $result['status']);
        $this->assertNotEmpty($result['validation_errors']);
    }

    public function test_misaligned_concept_rejected(): void
    {
        $this->bindGenerator(function (array $specification): array {
            $candidate = $this->validCandidateFromSpec($specification);
            $candidate['concept'] = 'functions';

            return $candidate;
        });

        $result = app(AiAssistedReassessmentService::class)
            ->createCandidateFromFinding($this->syntheticFinding(WeakAreaClassification::WeakPersistent));

        $this->assertSame(ReassessmentCandidateStatus::ValidationFailed->value, $result['status']);
        $this->assertContains('concept_misaligned', $result['validation_errors']);
    }

    public function test_wrong_bloom_demand_rejected(): void
    {
        $this->bindGenerator(function (array $specification): array {
            $candidate = $this->validCandidateFromSpec($specification);
            $candidate['bloom_demand'] = 'create';

            return $candidate;
        });

        $result = app(AiAssistedReassessmentService::class)
            ->createCandidateFromFinding($this->syntheticFinding(WeakAreaClassification::WeakPersistent));

        $this->assertContains('bloom_demand_mismatch', $result['validation_errors']);
    }

    public function test_wrong_dave_demand_rejected(): void
    {
        $this->bindGenerator(function (array $specification): array {
            $candidate = $this->validCandidateFromSpec($specification);
            $candidate['dave_demand'] = 'naturalization';

            return $candidate;
        });

        $result = app(AiAssistedReassessmentService::class)
            ->createCandidateFromFinding($this->syntheticFinding(WeakAreaClassification::WeakPersistent));

        $this->assertContains('dave_demand_mismatch', $result['validation_errors']);
    }

    public function test_missing_required_field_rejected(): void
    {
        $validator = app(ReassessmentCandidateValidator::class);
        $spec = app(ReassessmentSpecificationBuilder::class)->build(
            $this->syntheticFinding(WeakAreaClassification::WeakPersistent),
            [
                'title' => 'Original Loop Drill',
                'concept' => 'loops',
                'learning_objective' => 'Write a loop that iterates a list.',
                'bloom_demand' => 'apply',
                'dave_demand' => 'manipulation',
            ],
        );

        $candidate = $this->validCandidateFromSpec($spec);
        unset($candidate['task_prompt']);

        $validation = $validator->validate($spec, $candidate);
        $this->assertFalse($validation['valid']);
        $this->assertTrue(
            collect($validation['errors'])->contains(fn (string $error): bool => str_contains($error, 'task_prompt'))
        );
    }

    public function test_ai_unavailable_handled_safely(): void
    {
        $this->bindGenerator(function (): array {
            throw new ReassessmentGenerationException('AI provider unavailable', 'ai_unavailable');
        });

        $finding = $this->syntheticFinding(WeakAreaClassification::WeakPersistent);
        $beforeEvents = LearningEvent::query()->count();
        $result = app(AiAssistedReassessmentService::class)->createCandidateFromFinding($finding);

        $this->assertSame(ReassessmentCandidateStatus::GenerationFailed->value, $result['status']);
        $this->assertStringContainsString('unavailable', strtolower((string) $result['failure_reason']));
        $this->assertSame($beforeEvents, LearningEvent::query()->count());
        $this->assertFalse($result['delivered_to_learner']);
    }

    public function test_generation_timeout_handled_safely(): void
    {
        $this->bindGenerator(function (): array {
            throw new ReassessmentGenerationException('AI generation timed out', 'timeout');
        });

        $result = app(AiAssistedReassessmentService::class)
            ->createCandidateFromFinding($this->syntheticFinding(WeakAreaClassification::WeakPersistent));

        $this->assertSame(ReassessmentCandidateStatus::GenerationFailed->value, $result['status']);
        $record = ReassessmentCandidate::query()->first();
        $this->assertSame('timeout', $record->generation_metadata['failure_code']);
        $this->assertNull($result['candidate']);
    }

    public function test_provenance_preserved(): void
    {
        $this->seedPersistentWeakArea();
        $result = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');

        $this->assertNotEmpty($result['provenance']['learning_state_ids']);
        $this->assertNotEmpty($result['provenance']['validated_evidence_ids']);
        $this->assertNotEmpty($result['provenance']['activity_ids']);
        $this->assertSame(WeakAreaClassification::WeakPersistent->value, $result['provenance']['weak_area_classification']);

        $evidence = ValidatedEvidence::query()->findOrFail($result['provenance']['validated_evidence_ids'][0]);
        $this->assertTrue($evidence->learningEvent()->exists());
    }

    public function test_deterministic_repeated_execution_is_idempotent(): void
    {
        $this->seedPersistentWeakArea();

        $first = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');
        $second = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');

        $this->assertSame($first['candidate_key'], $second['candidate_key']);
        $this->assertSame($first['candidate_id'], $second['candidate_id']);
        $this->assertSame(1, ReassessmentCandidate::query()->count());
    }

    public function test_no_learner_delivery_and_no_source_of_truth_mutation(): void
    {
        $this->seedPersistentWeakArea();
        $before = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
        ];

        $result = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');

        $this->assertFalse($result['delivered_to_learner']);
        $this->assertFalse($result['creates_learning_event']);
        $this->assertFalse($result['creates_validated_evidence']);
        $this->assertFalse($result['creates_learning_state']);
        $this->assertTrue($result['source_of_truth_unchanged']);
        $this->assertSame($before['events'], LearningEvent::query()->count());
        $this->assertSame($before['evidence'], ValidatedEvidence::query()->count());
        $this->assertSame($before['states'], LearningState::query()->count());
        $this->assertFalse(class_exists('App\\Http\\Controllers\\Student\\ReassessmentDeliveryController'));
    }

    public function test_scope_fence_no_m5_05_06_07_or_psychological_inference(): void
    {
        $this->seedPersistentWeakArea();
        $result = $this->service->createCandidateForLearningArea($this->student->id, $this->course->id, 'concept:loops');

        $this->assertFalse($result['analysis_boundary']['performs_improvement_analysis']);
        $this->assertFalse($result['analysis_boundary']['performs_contextual_variation_analysis']);
        $this->assertFalse($result['analysis_boundary']['performs_research_export']);
        $this->assertFalse($result['analysis_boundary']['claims_psychological_diagnosis']);
        $this->assertFalse($result['analysis_boundary']['llm_is_final_decision_maker']);
        $this->assertFalse($result['claims_improvement']);
        $this->assertFalse($result['claims_effectiveness']);
        $this->assertFalse(class_exists('App\\Services\\Research\\InterventionEffectivenessService'));
        $this->assertFalse(class_exists('App\\Services\\Research\\ContextualVariationService'));
        $this->assertFalse($result['analysis_boundary']['performs_research_export']);
        $this->assertFalse(class_exists('App\\Services\\Research\\ReassessmentQuestionGenerator'));
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    private function bindGenerator(callable $callback): void
    {
        $this->app->instance(ReassessmentCandidateGenerator::class, new class($callback) implements ReassessmentCandidateGenerator
        {
            public function __construct(private $callback) {}

            public function generate(array $specification): array
            {
                return ($this->callback)($specification);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $specification
     * @return array<string, mixed>
     */
    private function validCandidateFromSpec(array $specification): array
    {
        return [
            'title' => 'Reassessment candidate for '.($specification['concept'] ?? 'topic'),
            'task_prompt' => 'Solve a new '.$specification['concept'].' problem that differs from prior activities.',
            'scenario' => 'New scenario for '.$specification['concept'],
            'concept' => $specification['concept'],
            'learning_objective' => $specification['learning_objective'] ?? null,
            'bloom_demand' => $specification['bloom_demand'],
            'dave_demand' => $specification['dave_demand'] ?? null,
            'task_format' => $specification['constraints']['task_format'] ?? 'coding_exercise',
            'expected_outcome' => 'Demonstrate '.$specification['concept'],
            'rubric' => 'Check correct use of '.$specification['concept'],
            'includes_direct_answer' => false,
            'generator_identity' => 'test_fake_generator',
            'generator_model' => 'fake-v1',
            'metadata' => ['ai_assisted' => true, 'llm_decision_maker' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syntheticFinding(WeakAreaClassification $classification): array
    {
        $state = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'state' => $classification === WeakAreaClassification::NoCurrentWeakness
                ? LearningStateValue::Stable
                : LearningStateValue::NeedsSupport,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => now()->subMinutes(10),
            'inference_key' => hash('sha256', uniqid('m5-04', true)),
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        $evidence = $this->makeEvidence('submission_rejected', now()->subMinutes(10));
        $state->validatedEvidence()->sync([$evidence->id]);

        return [
            'research_learner_id' => hash('sha256', 'x'),
            'learner_id' => $this->student->id,
            'course_id' => $this->course->id,
            'learning_area_key' => 'concept:loops',
            'learning_area_label' => 'loops',
            'learning_area_representation' => 'activity_concept',
            'classification' => $classification->value,
            'is_weak_area' => in_array($classification, [
                WeakAreaClassification::WeakPersistent,
                WeakAreaClassification::WeakRepeatedFailure,
                WeakAreaClassification::WeakUnresolved,
            ], true),
            'supporting_evidence_ids' => [$evidence->id],
            'supporting_learning_state_ids' => [$state->id],
            'activity_ids' => [$this->activity->id],
            'trajectory' => ['sequence' => ['needs_support', 'needs_support'], 'transitions' => []],
            'bloom_demand_context' => ['apply'],
            'dave_demand_context' => ['manipulation'],
            'evidence_quality_summary' => ['valid' => 1],
            'evidence_confidence_summary' => ['high' => 1],
            'detection_rule' => 'fixture',
            'explanation' => 'Fixture weak-area finding for M5-04.',
        ];
    }

    private function seedPersistentWeakArea(): void
    {
        foreach ([40, 30, 20] as $minutes) {
            $state = LearningState::factory()->create([
                'user_id' => $this->student->id,
                'activity_id' => $this->activity->id,
                'state' => LearningStateValue::NeedsSupport,
                'state_confidence' => StateConfidence::Medium,
                'inferred_at' => now()->subMinutes($minutes),
                'inference_key' => hash('sha256', uniqid('persist-'.$minutes, true)),
                'cognitive_indicator' => 'unresolved_performance_outcome_observed',
                'behavioral_indicators' => ['persistent_attempt_behavior'],
                'bloom_demand' => BloomLevel::Apply,
                'dave_demand' => DaveLevel::Manipulation,
                'explanation' => 'Fixture needs_support',
                'inference_rule' => 'fixture',
            ]);
            $evidence = $this->makeEvidence('submission_rejected', now()->subMinutes($minutes));
            $state->validatedEvidence()->sync([$evidence->id]);
        }
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
            'validation_reason' => 'Fixture M5-04',
            'validated_at' => $at,
        ]);
    }
}
