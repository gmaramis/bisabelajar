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
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\StateConfidence;
use App\Enums\WeakAreaClassification;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\NextLearningAction;
use App\Models\ReassessmentCandidate;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\ResearchEvidenceExportService;
use App\Services\Research\ResearchEvidenceQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ResearchEvidenceExportTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Activity $activity;

    private ResearchEvidenceExportService $export;

    private ResearchEvidenceQuery $research;

    private LearningState $beforeState;

    private LearningState $afterState;

    private AdaptiveIntervention $intervention;

    private NextLearningAction $nextAction;

    private ReassessmentCandidate $reassessment;

    protected function setUp(): void
    {
        parent::setUp();

        $tutor = User::factory()->tutor()->create();
        $this->student = User::factory()->student()->create([
            'name' => 'Secret Name',
            'email' => 'secret.export@example.com',
        ]);
        $this->course = Course::factory()->for($tutor, 'owner')->published()->public()->create([
            'title' => 'Export Course',
        ]);
        $module = Module::factory()->for($this->course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $this->activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        $this->export = app(ResearchEvidenceExportService::class);
        $this->research = app(ResearchEvidenceQuery::class);

        $this->beforeState = $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(40), 'submission_rejected');
        $this->intervention = AdaptiveIntervention::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_state_id' => $this->beforeState->id,
            'intervention_type' => InterventionType::GuidedRetry,
            'is_remedial' => true,
            'intervention_key' => hash('sha256', 'export-intervention'),
            'metadata' => [
                'validated_evidence_ids' => $this->beforeState->validatedEvidence->pluck('id')->all(),
            ],
        ]);
        $this->intervention->forceFill([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ])->save();

        $this->afterState = $this->makeState(LearningStateValue::Progressing, now()->subMinutes(10), 'submission_accepted');
        $this->nextAction = NextLearningAction::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_state_id' => $this->afterState->id,
            'adaptive_intervention_id' => $this->intervention->id,
            'action' => NextLearningActionType::Continue,
            'retry_outcome' => 'success',
            'decision_key' => hash('sha256', 'export-next-action'),
            'decided_at' => now()->subMinutes(8),
        ]);

        // Extra needs_support history for weak-area eligibility on concept:loops
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(50), 'submission_rejected');
        $this->makeState(LearningStateValue::NeedsSupport, now()->subMinutes(45), 'submission_rejected');

        $this->reassessment = ReassessmentCandidate::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'research_learner_id' => $this->research->researchLearnerId($this->student->id),
            'learning_area_key' => 'concept:loops',
            'learning_area_label' => 'loops',
            'weak_area_classification' => WeakAreaClassification::WeakPersistent,
            'concept' => 'loops',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'status' => ReassessmentCandidateStatus::Validated,
            'candidate_key' => hash('sha256', 'export-reassessment'),
            'generated_at' => now()->subMinutes(5),
            'validated_at' => now()->subMinutes(4),
        ]);
    }

    public function test_export_contains_schema_version_and_manifest(): void
    {
        $result = $this->export->export($this->student->id, $this->course->id);

        $this->assertSame(ResearchEvidenceExportService::SCHEMA_VERSION, $result['schema_version']);
        $this->assertSame('m5-07.v1', $result['schema_version']);
        $this->assertSame(ResearchEvidenceExportService::SCHEMA_VERSION, $result['manifest']['schema_version']);
        $this->assertArrayHasKey('generated_at', $result['manifest']);
        $this->assertArrayHasKey('ordering_rule', $result['manifest']);
        $this->assertArrayHasKey('privacy_policy_summary', $result['manifest']);
        $this->assertArrayHasKey('provenance_policy', $result['manifest']);
        $this->assertSame(count($result['records']), $result['manifest']['record_count']);
    }

    public function test_export_uses_research_learner_id_and_excludes_pii(): void
    {
        $result = $this->export->export($this->student->id, $this->course->id);
        $encoded = json_encode($result);

        $this->assertNotEmpty($result['records']);
        $this->assertSame(
            $this->research->researchLearnerId($this->student->id),
            $result['records'][0]['research_learner_id'],
        );
        $this->assertSame(
            $this->research->researchLearnerId($this->student->id),
            $result['export_scope']['research_learner_id'],
        );
        $this->assertArrayNotHasKey('user_id', $result['export_scope']);
        $this->assertStringNotContainsString('Secret Name', $encoded);
        $this->assertStringNotContainsString('secret.export@example.com', $encoded);
        $this->assertStringNotContainsString('"password"', $encoded);
        $this->assertDoesNotMatchRegularExpression('/"user_agent"\s*:/', $encoded);
        $this->assertDoesNotMatchRegularExpression('/"ip_address"\s*:/', $encoded);
        $this->assertFalse($result['records'][0]['privacy']['includes_email']);
        $this->assertFalse($result['records'][0]['privacy']['includes_authentication_id']);
        $this->assertFalse($result['records'][0]['privacy']['includes_ip_or_user_agent']);
    }

    public function test_export_contains_core_m5_layers(): void
    {
        $result = $this->export->export($this->student->id, $this->course->id);
        $record = collect($result['records'])->first(
            fn (array $row): bool => ($row['learning_state']['learning_state_id'] ?? null) === $this->afterState->id
        );

        $this->assertNotNull($record);
        $this->assertNotEmpty($record['learning_event']);
        $this->assertNotEmpty($record['validated_evidence']);
        $this->assertSame(LearningStateValue::Progressing->value, $record['learning_state']['state']);
        $this->assertNotEmpty($record['trajectory']['sequence']);
        $this->assertNotNull($record['weak_area']);
        $this->assertNotNull($record['reassessment']);
        $this->assertNotNull($record['contextual_variation']['course_summaries']);

        $withIntervention = collect($result['records'])->first(
            fn (array $row): bool => ($row['learning_state']['learning_state_id'] ?? null) === $this->beforeState->id
        );
        $this->assertNotNull($withIntervention['intervention']);
        $this->assertNotNull($withIntervention['intervention_response']);
        $this->assertTrue(
            collect($result['records'])->contains(
                fn (array $row): bool => is_array($row['next_learning_action'] ?? null)
            )
        );
        $this->assertTrue(
            collect($result['records'])->contains(
                fn (array $row): bool => ($row['intervention_response'][0]['observed_improvement'] ?? false) === true
                    || ($row['intervention_response'][0]['observed_improvement_signal'] ?? null) !== null
            ) || collect($result['records'])->contains(
                fn (array $row): bool => is_array($row['intervention_response'] ?? null)
            )
        );
    }

    public function test_provenance_bloom_dave_and_determinism(): void
    {
        $first = $this->export->export($this->student->id, $this->course->id);
        $second = $this->export->export($this->student->id, $this->course->id);

        $record = $first['records'][0];
        $this->assertArrayHasKey('learning_state_id', $record['provenance']);
        $this->assertNotEmpty($record['provenance']['validated_evidence_ids']);
        $this->assertSame('task_demand', $record['learning_state']['bloom_semantics']);
        $this->assertSame('task_demand', $record['context']['dave_semantics']);
        $this->assertSame('apply', $record['context']['bloom_demand']);

        $firstIds = array_map(fn (array $row): int => $row['learning_state']['learning_state_id'], $first['records']);
        $secondIds = array_map(fn (array $row): int => $row['learning_state']['learning_state_id'], $second['records']);
        $this->assertSame($firstIds, $secondIds);
        $this->assertSame($first['jsonl'], $second['jsonl']);
        $this->assertSame($first['manifest']['record_count'], $second['manifest']['record_count']);
    }

    public function test_jsonl_and_csv_share_canonical_records(): void
    {
        $result = $this->export->export($this->student->id, $this->course->id);

        $lines = array_values(array_filter(explode("\n", trim($result['jsonl']))));
        $this->assertCount(count($result['records']), $lines);
        $decoded = json_decode($lines[0], true);
        $this->assertSame($result['records'][0]['research_learner_id'], $decoded['research_learner_id']);

        $this->assertStringContainsString('schema_version', $result['csv']);
        $this->assertStringContainsString('research_learner_id', $result['csv']);
        $this->assertStringContainsString('task_demand', $result['csv']);
    }

    public function test_empty_dataset_and_invalid_scope(): void
    {
        $otherCourse = Course::factory()->for(User::factory()->tutor()->create(), 'owner')->published()->public()->create();
        $empty = $this->export->export($this->student->id, $otherCourse->id);

        $this->assertSame([], $empty['records']);
        $this->assertSame(0, $empty['manifest']['record_count']);
        $this->assertSame('', $empty['jsonl']);

        $this->expectException(InvalidArgumentException::class);
        $this->export->export(null, null);
    }

    public function test_learner_course_and_combined_scopes(): void
    {
        $learnerOnly = $this->export->export($this->student->id, null);
        $courseOnly = $this->export->export(null, $this->course->id);
        $combined = $this->export->export($this->student->id, $this->course->id);

        $this->assertSame('learner', $learnerOnly['export_scope']['type']);
        $this->assertSame('course', $courseOnly['export_scope']['type']);
        $this->assertSame('learner_course', $combined['export_scope']['type']);
        $this->assertNotEmpty($learnerOnly['records']);
        $this->assertNotEmpty($courseOnly['records']);
        $this->assertNotEmpty($combined['records']);
    }

    public function test_no_source_mutation_and_no_m6_or_statistics(): void
    {
        $counts = [
            'events' => LearningEvent::query()->count(),
            'evidence' => ValidatedEvidence::query()->count(),
            'states' => LearningState::query()->count(),
            'interventions' => AdaptiveIntervention::query()->count(),
            'actions' => NextLearningAction::query()->count(),
            'reassessments' => ReassessmentCandidate::query()->count(),
        ];

        $result = $this->export->export($this->student->id, $this->course->id);

        $this->assertSame($counts['events'], LearningEvent::query()->count());
        $this->assertSame($counts['evidence'], ValidatedEvidence::query()->count());
        $this->assertSame($counts['states'], LearningState::query()->count());
        $this->assertSame($counts['interventions'], AdaptiveIntervention::query()->count());
        $this->assertSame($counts['actions'], NextLearningAction::query()->count());
        $this->assertSame($counts['reassessments'], ReassessmentCandidate::query()->count());
        $this->assertFalse($result['analysis_boundary']['mutates_source_data']);
        $this->assertFalse($result['analysis_boundary']['performs_statistical_analysis']);
        $this->assertFalse($result['analysis_boundary']['performs_causal_inference']);
        $this->assertFalse($result['analysis_boundary']['implements_m6']);
        $this->assertFalse($result['analysis_boundary']['generates_research_paper']);
        $this->assertFalse($result['manifest']['claims_causal_or_statistical_analysis']);
        $this->assertFalse(class_exists('App\\Services\\M6\\M6BootstrapService'));
    }

    public function test_m5_layer_integration_flags_in_manifest(): void
    {
        $result = $this->export->export($this->student->id, $this->course->id);
        $layers = implode(' ', $result['manifest']['source_layers']);

        $this->assertStringContainsString('M5-01', $layers);
        $this->assertStringContainsString('M5-02', $layers);
        $this->assertStringContainsString('M5-03', $layers);
        $this->assertStringContainsString('M5-04', $layers);
        $this->assertStringContainsString('M5-05', $layers);
        $this->assertStringContainsString('M5-06', $layers);

        $reassessmentIds = collect($result['records'])->flatMap(
            fn (array $row) => $row['provenance']['reassessment_candidate_ids'] ?? []
        )->all();
        $nextActionIds = collect($result['records'])->flatMap(
            fn (array $row) => $row['provenance']['next_learning_action_ids'] ?? []
        )->all();

        $this->assertContains($this->reassessment->id, $reassessmentIds);
        $this->assertContains($this->nextAction->id, $nextActionIds);
        $this->assertTrue($result['manifest']['data_gaps']['campus']);
    }

    private function makeState(LearningStateValue $state, $at, string $evidenceType): LearningState
    {
        $record = LearningState::factory()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $at,
            'inference_key' => hash('sha256', uniqid($state->value, true)),
            'cognitive_indicator' => $state === LearningStateValue::NeedsSupport
                ? 'unresolved_performance_outcome_observed'
                : 'successful_task_outcome_observed',
            'behavioral_indicators' => ['persistent_attempt_behavior'],
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'explanation' => 'Fixture M5-07',
            'inference_rule' => 'fixture_m5_07',
        ]);

        $event = LearningEvent::query()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'activity_id' => $this->activity->id,
            'event_type' => $evidenceType,
            'payload' => ['seeded' => true],
            'occurred_at' => $at,
        ]);

        $evidence = ValidatedEvidence::query()->create([
            'user_id' => $this->student->id,
            'activity_id' => $this->activity->id,
            'learning_event_id' => $event->id,
            'evidence_category' => EvidenceCategory::Performance->value,
            'evidence_type' => $evidenceType,
            'observed_value' => ['summary' => $evidenceType],
            'context_summary' => [],
            'quality' => EvidenceQuality::Valid->value,
            'confidence' => EvidenceConfidence::High->value,
            'validation_reason' => 'Fixture M5-07',
            'validated_at' => $at,
        ]);

        $record->validatedEvidence()->sync([$evidence->id]);

        return $record->fresh([
            'validatedEvidence.learningEvent',
            'activity.learningUnit.module.course',
            'adaptiveInterventions',
            'nextLearningActions',
        ]);
    }
}
