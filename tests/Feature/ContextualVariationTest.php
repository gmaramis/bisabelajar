<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\ContextDimension;
use App\Enums\ContextEvidenceSufficiency;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Models\Activity;
use App\Models\AdaptiveIntervention;
use App\Models\Course;
use App\Models\LanguageExecutionProfile;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\ProgrammingActivity;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Research\ContextualVariationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContextualVariationTest extends TestCase
{
    use RefreshDatabase;

    private User $tutor;

    private ContextualVariationQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tutor = User::factory()->tutor()->create();
        $this->query = app(ContextualVariationQuery::class);
    }

    public function test_course_context_dimension(): void
    {
        [$course, $activity] = $this->seedCourseTree('Course A');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::NeedsSupport);
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Progressing);

        $result = $this->query->forCourse($course->id, ContextDimension::Course);

        $this->assertSame('course', $result['dimension']);
        $this->assertSame($course->id, $result['scope']['course_id']);
        $this->assertNotEmpty($result['contexts']);
        $this->assertSame('course:'.$course->id, $result['contexts'][0]['context_key']);
        $this->assertSame(2, $result['contexts'][0]['learner_count']);
        $this->assertSame(2, $result['contexts'][0]['observation_count']);
    }

    public function test_module_and_learning_unit_and_activity_contexts(): void
    {
        [$course, $activity, $module, $unit] = $this->seedCourseTreeFull('Ctx Course');
        $learner = User::factory()->student()->create();
        $this->seedState($activity, $learner, LearningStateValue::Stable);

        $moduleResult = $this->query->forCourse($course->id, ContextDimension::Module);
        $unitResult = $this->query->forCourse($course->id, ContextDimension::LearningUnit);
        $activityResult = $this->query->forCourse($course->id, ContextDimension::Activity);

        $this->assertSame('course:'.$course->id.'|module:'.$module->id, $moduleResult['contexts'][0]['context_key']);
        $this->assertSame('course:'.$course->id.'|learning_unit:'.$unit->id, $unitResult['contexts'][0]['context_key']);
        $this->assertSame('course:'.$course->id.'|activity:'.$activity->id, $activityResult['contexts'][0]['context_key']);
    }

    public function test_programming_language_context_without_hardcoding(): void
    {
        [$course, $pythonActivity] = $this->seedCourseTree('Lang Course');
        $cppActivity = $this->makeActivity($course, 'cpp-activity', BloomLevel::Apply, DaveLevel::Manipulation);
        $this->attachLanguage($pythonActivity, 'python-research', 'Python Research');
        $this->attachLanguage($cppActivity, 'cpp-research', 'C++ Research');

        $a = User::factory()->student()->create();
        $b = User::factory()->student()->create();
        $this->seedState($pythonActivity, $a, LearningStateValue::NeedsSupport);
        $this->seedState($pythonActivity, $b, LearningStateValue::NeedsSupport);
        $this->seedState($pythonActivity, $a, LearningStateValue::Progressing);
        $this->seedState($cppActivity, $a, LearningStateValue::Stable);
        $this->seedState($cppActivity, $b, LearningStateValue::Progressing);
        $this->seedState($cppActivity, $a, LearningStateValue::Progressing);

        $result = $this->query->forCourse($course->id, ContextDimension::ProgrammingLanguage);
        $keys = array_column($result['contexts'], 'context_key');

        $this->assertContains('course:'.$course->id.'|language:python-research', $keys);
        $this->assertContains('course:'.$course->id.'|language:cpp-research', $keys);
        $this->assertTrue($result['variation_summary']['observed_variation']);
        $this->assertStringContainsString('observed contextual variation', strtolower($result['variation_summary']['explanation']));
        $this->assertStringNotContainsString('c++ produces better', strtolower($result['variation_summary']['explanation']));
        $this->assertFalse($result['variation_summary']['claims_context_caused_outcome']);
    }

    public function test_bloom_and_dave_task_demand_contexts(): void
    {
        [$course] = $this->seedCourseTree('Demand Course');
        $apply = $this->makeActivity($course, 'apply-act', BloomLevel::Apply, DaveLevel::Manipulation);
        $analyze = $this->makeActivity($course, 'analyze-act', BloomLevel::Analyze, DaveLevel::Precision);
        $learnerA = User::factory()->student()->create();
        $learnerB = User::factory()->student()->create();

        $this->seedState($apply, $learnerA, LearningStateValue::NeedsSupport);
        $this->seedState($apply, $learnerB, LearningStateValue::NeedsSupport);
        $this->seedState($apply, $learnerA, LearningStateValue::NeedsSupport);
        $this->seedState($analyze, $learnerA, LearningStateValue::Progressing);
        $this->seedState($analyze, $learnerB, LearningStateValue::Stable);
        $this->seedState($analyze, $learnerA, LearningStateValue::Stable);

        $bloom = $this->query->forCourse($course->id, ContextDimension::BloomTaskDemand);
        $dave = $this->query->forCourse($course->id, ContextDimension::DaveTaskDemand);

        $this->assertTrue(collect($bloom['contexts'])->every(
            fn (array $row): bool => $row['bloom_semantics'] === 'task_demand'
        ));
        $this->assertTrue(collect($dave['contexts'])->every(
            fn (array $row): bool => $row['dave_semantics'] === 'task_demand'
        ));
        $this->assertTrue($bloom['variation_summary']['observed_variation']);
        $this->assertStringContainsString('task-demand', strtolower(str_replace('_', '-', $bloom['dimension'])));
        $this->assertStringNotContainsString('causes better learning', strtolower($bloom['variation_summary']['explanation']));
    }

    public function test_missing_programming_language_is_unavailable_bucket(): void
    {
        [$course, $activity] = $this->seedCourseTree('No Lang');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Stable);

        $result = $this->query->forCourse($course->id, ContextDimension::ProgrammingLanguage);

        $this->assertSame('unavailable', $result['contexts'][0]['context_key']);
        $this->assertSame(ContextEvidenceSufficiency::InsufficientEvidence->value, $result['contexts'][0]['evidence_sufficiency']);
    }

    public function test_session_sparsity_is_reported_and_not_used_as_dimension(): void
    {
        [$course, $activity] = $this->seedCourseTree('Session Sparse');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Stable);

        $result = $this->query->forCourse($course->id, ContextDimension::Course);
        $available = $this->query->availableDimensions();

        $this->assertFalse($result['session_sparsity']['used_as_dimension']);
        $this->assertSame(0, $result['session_sparsity']['populated_session_event_count']);
        $this->assertArrayHasKey('session', $available['sparse_or_limited']);
        $this->assertNotContains('session', $available['available']);
    }

    public function test_small_sample_handling(): void
    {
        [$course, $activity] = $this->seedCourseTree('Small Sample');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::NeedsSupport);

        $result = $this->query->forCourse($course->id, ContextDimension::Activity);

        $this->assertSame(ContextEvidenceSufficiency::LimitedContextEvidence->value, $result['contexts'][0]['evidence_sufficiency']);
        $this->assertSame(1, $result['contexts'][0]['learner_count']);
        $this->assertSame(1, $result['contexts'][0]['observation_count']);
    }

    public function test_learner_overlap_preserves_distinct_counts(): void
    {
        [$course] = $this->seedCourseTree('Overlap');
        $python = $this->makeActivity($course, 'py', BloomLevel::Apply, DaveLevel::Manipulation);
        $cpp = $this->makeActivity($course, 'cpp', BloomLevel::Apply, DaveLevel::Manipulation);
        $this->attachLanguage($python, 'python-x', 'Python X');
        $this->attachLanguage($cpp, 'cpp-x', 'C++ X');

        $shared = User::factory()->student()->create();
        $this->seedState($python, $shared, LearningStateValue::NeedsSupport);
        $this->seedState($python, $shared, LearningStateValue::Progressing);
        $this->seedState($cpp, $shared, LearningStateValue::Stable);
        $this->seedState($cpp, User::factory()->student()->create(), LearningStateValue::Progressing);
        $this->seedState($cpp, User::factory()->student()->create(), LearningStateValue::Stable);

        $result = $this->query->forCourse($course->id, ContextDimension::ProgrammingLanguage);
        $pythonBucket = collect($result['contexts'])->firstWhere('context_label', 'Python X');
        $cppBucket = collect($result['contexts'])->firstWhere('context_label', 'C++ X');

        $this->assertSame(1, $pythonBucket['learner_count']);
        $this->assertSame(2, $pythonBucket['observation_count']);
        $this->assertSame(3, $cppBucket['learner_count']);
        $this->assertStringContainsString('same learner may appear', strtolower($pythonBucket['unit_note']));
    }

    public function test_course_separation_does_not_merge_unrelated_courses(): void
    {
        [$courseA, $activityA] = $this->seedCourseTree('Course A Lang');
        [$courseB, $activityB] = $this->seedCourseTree('Course B Lang');
        $this->attachLanguage($activityA, 'python-course-a', 'Python Shared');
        $this->attachLanguage($activityB, 'python-course-b', 'Python Shared');

        $this->seedState($activityA, User::factory()->student()->create(), LearningStateValue::NeedsSupport);
        $this->seedState($activityA, User::factory()->student()->create(), LearningStateValue::NeedsSupport);
        $this->seedState($activityA, User::factory()->student()->create(), LearningStateValue::NeedsSupport);
        $this->seedState($activityB, User::factory()->student()->create(), LearningStateValue::Stable);
        $this->seedState($activityB, User::factory()->student()->create(), LearningStateValue::Stable);
        $this->seedState($activityB, User::factory()->student()->create(), LearningStateValue::Stable);

        $resultA = $this->query->forCourse($courseA->id, ContextDimension::ProgrammingLanguage);
        $resultB = $this->query->forCourse($courseB->id, ContextDimension::ProgrammingLanguage);

        $this->assertSame(
            'course:'.$courseA->id.'|language:python-course-a',
            $resultA['contexts'][0]['context_key'],
        );
        $this->assertSame(
            'course:'.$courseB->id.'|language:python-course-b',
            $resultB['contexts'][0]['context_key'],
        );
        $this->assertSame(3, $resultA['contexts'][0]['state_distribution']['needs_support']);
        $this->assertSame(3, $resultB['contexts'][0]['state_distribution']['stable']);
        $this->assertNotEquals(
            $resultA['contexts'][0]['state_distribution'],
            $resultB['contexts'][0]['state_distribution'],
        );
    }

    public function test_state_distribution_weak_area_and_intervention_response_variation(): void
    {
        [$course] = $this->seedCourseTree('Metrics');
        $left = $this->makeActivity($course, 'left', BloomLevel::Apply, DaveLevel::Manipulation, 'loops');
        $right = $this->makeActivity($course, 'right', BloomLevel::Apply, DaveLevel::Manipulation, 'loops');
        $this->attachLanguage($left, 'python-m', 'Python M');
        $this->attachLanguage($right, 'cpp-m', 'C++ M');

        $learners = [
            User::factory()->student()->create(),
            User::factory()->student()->create(),
            User::factory()->student()->create(),
        ];

        foreach ($learners as $learner) {
            $before = $this->seedState($left, $learner, LearningStateValue::NeedsSupport, withEvidence: true);
            $intervention = AdaptiveIntervention::factory()->create([
                'user_id' => $learner->id,
                'activity_id' => $left->id,
                'learning_state_id' => $before->id,
                'intervention_type' => InterventionType::GuidedRetry,
                'is_remedial' => true,
                'intervention_key' => hash('sha256', uniqid('left', true)),
                'metadata' => ['validated_evidence_ids' => $before->validatedEvidence->pluck('id')->all()],
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(20),
            ]);
            $intervention->forceFill(['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)])->save();
            $this->seedState($left, $learner, LearningStateValue::NeedsSupport, withEvidence: true, evidenceType: 'submission_rejected', at: now()->subMinutes(5));
        }

        foreach ($learners as $learner) {
            $before = $this->seedState($right, $learner, LearningStateValue::NeedsSupport, withEvidence: true);
            $intervention = AdaptiveIntervention::factory()->create([
                'user_id' => $learner->id,
                'activity_id' => $right->id,
                'learning_state_id' => $before->id,
                'intervention_type' => InterventionType::GuidedRetry,
                'is_remedial' => true,
                'intervention_key' => hash('sha256', uniqid('right', true)),
                'metadata' => ['validated_evidence_ids' => $before->validatedEvidence->pluck('id')->all()],
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(20),
            ]);
            $intervention->forceFill(['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)])->save();
            $this->seedState($right, $learner, LearningStateValue::Progressing, withEvidence: true, evidenceType: 'submission_accepted', at: now()->subMinutes(5));
        }

        $result = $this->query->forCourse($course->id, ContextDimension::ProgrammingLanguage);
        $python = collect($result['contexts'])->firstWhere('context_label', 'Python M');
        $cpp = collect($result['contexts'])->firstWhere('context_label', 'C++ M');

        $this->assertGreaterThan(0, $python['state_distribution']['needs_support']);
        $this->assertGreaterThan(0, $cpp['state_distribution']['progressing']);
        $this->assertGreaterThan(0, $python['intervention_summary']['intervention_count']);
        $this->assertGreaterThan(0, $cpp['intervention_summary']['intervention_count']);
        $this->assertArrayHasKey('weak_area_count', $python['weak_area_summary']);
        $this->assertTrue($result['variation_summary']['observed_variation']);
    }

    public function test_evidence_quality_confidence_and_provenance(): void
    {
        [$course, $activity] = $this->seedCourseTree('Prov');
        $learner = User::factory()->student()->create();
        $state = $this->seedState($activity, $learner, LearningStateValue::Stable, withEvidence: true);

        $result = $this->query->forCourse($course->id, ContextDimension::Course);
        $bucket = $result['contexts'][0];

        $this->assertGreaterThan(0, $bucket['evidence_quality_summary']['valid']);
        $this->assertGreaterThan(0, $bucket['evidence_confidence_summary']['high']);
        $this->assertContains($state->id, $bucket['provenance']['learning_state_ids']);
        $this->assertNotEmpty($bucket['provenance']['validated_evidence_ids']);
        $this->assertContains($activity->id, $bucket['provenance']['activity_ids']);
        $this->assertNotEmpty($bucket['research_learner_ids']);
        $this->assertStringNotContainsString('@', implode(',', $bucket['research_learner_ids']));
    }

    public function test_deterministic_repeated_execution(): void
    {
        [$course, $activity] = $this->seedCourseTree('Deterministic');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Progressing);
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Stable);

        $first = $this->query->forCourse($course->id, ContextDimension::Course);
        $second = $this->query->forCourse($course->id, ContextDimension::Course);

        $this->assertSame($first['contexts'][0]['context_key'], $second['contexts'][0]['context_key']);
        $this->assertSame($first['contexts'][0]['state_distribution'], $second['contexts'][0]['state_distribution']);
        $this->assertSame($first['variation_summary']['observed_variation'], $second['variation_summary']['observed_variation']);
    }

    public function test_no_fabricated_campus_institution_cohort_and_no_causal_or_export(): void
    {
        [$course, $activity] = $this->seedCourseTree('Gaps');
        $this->seedState($activity, User::factory()->student()->create(), LearningStateValue::Stable);

        $result = $this->query->forCourse($course->id, ContextDimension::Course);
        $available = $this->query->availableDimensions();

        $this->assertTrue($result['data_gaps']['campus']);
        $this->assertTrue($result['data_gaps']['institution']);
        $this->assertTrue($result['data_gaps']['cohort']);
        $this->assertSame('not_in_schema', $available['unavailable']['campus']);
        $this->assertFalse($result['analysis_boundary']['fabricates_campus_institution_cohort']);
        $this->assertFalse($result['analysis_boundary']['performs_causal_inference']);
        $this->assertFalse($result['analysis_boundary']['calculates_p_values']);
        $this->assertFalse($result['analysis_boundary']['performs_research_export']);
        $this->assertFalse(Schema::hasTable('contextual_variations'));
        $this->assertFalse(Schema::hasTable('context_analysis'));
        $this->assertFalse($result['analysis_boundary']['performs_research_export']);
        $this->assertFalse($result['contexts'][0]['claims_context_caused_outcome']);
    }

    /**
     * @return array{0: Course, 1: Activity}
     */
    private function seedCourseTree(string $title): array
    {
        [$course, $activity] = array_slice($this->seedCourseTreeFull($title), 0, 2);

        return [$course, $activity];
    }

    /**
     * @return array{0: Course, 1: Activity, 2: Module, 3: LearningUnit}
     */
    private function seedCourseTreeFull(string $title): array
    {
        $course = Course::factory()->for($this->tutor, 'owner')->published()->public()->create(['title' => $title]);
        $module = Module::factory()->for($course)->published()->create(['title' => $title.' Module']);
        $unit = LearningUnit::factory()->for($module)->published()->create(['title' => $title.' Unit']);
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'title' => $title.' Activity',
            'concept' => 'loops',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);

        return [$course, $activity, $module, $unit];
    }

    private function makeActivity(
        Course $course,
        string $title,
        BloomLevel $bloom,
        DaveLevel $dave,
        string $concept = 'loops',
    ): Activity {
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();

        return Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'title' => $title,
            'concept' => $concept,
            'bloom_demand' => $bloom,
            'dave_demand' => $dave,
        ]);
    }

    private function attachLanguage(Activity $activity, string $identifier, string $displayName): void
    {
        $profile = LanguageExecutionProfile::query()->create([
            'identifier' => $identifier,
            'display_name' => $displayName,
            'file_extension' => '.src',
            'source_filename' => 'main.src',
            'docker_image' => 'lang:test',
            'compile_command' => null,
            'run_command' => 'run',
            'execution_mode' => 'interpreted',
            'timeout_seconds' => 10,
            'memory_limit_mb' => 64,
            'cpu_limit' => 1,
            'network_enabled' => false,
            'enabled' => true,
            'environment_variables' => [],
            'allowed_files' => [],
        ]);

        ProgrammingActivity::createForActivity($activity, $profile);
    }

    private function seedState(
        Activity $activity,
        User $learner,
        LearningStateValue $state,
        bool $withEvidence = false,
        string $evidenceType = 'submission_accepted',
        $at = null,
    ): LearningState {
        $at ??= now()->subMinutes(random_int(1, 50));

        $record = LearningState::factory()->create([
            'user_id' => $learner->id,
            'activity_id' => $activity->id,
            'state' => $state,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => $at,
            'inference_key' => hash('sha256', uniqid($state->value, true)),
            'bloom_demand' => $activity->bloom_demand,
            'dave_demand' => $activity->dave_demand,
            'cognitive_indicator' => $state === LearningStateValue::NeedsSupport
                ? 'unresolved_performance_outcome_observed'
                : 'successful_task_outcome_observed',
            'behavioral_indicators' => [],
            'explanation' => 'Fixture M5-06',
            'inference_rule' => 'fixture_m5_06',
        ]);

        if ($withEvidence) {
            $courseId = $activity->learningUnit()->first()?->module?->course_id
                ?? $activity->fresh('learningUnit.module')->learningUnit->module->course_id;

            $event = LearningEvent::query()->create([
                'user_id' => $learner->id,
                'course_id' => $courseId,
                'activity_id' => $activity->id,
                'event_type' => $evidenceType,
                'payload' => [],
                'occurred_at' => $at,
            ]);

            $evidence = ValidatedEvidence::query()->create([
                'user_id' => $learner->id,
                'activity_id' => $activity->id,
                'learning_event_id' => $event->id,
                'evidence_category' => EvidenceCategory::Performance->value,
                'evidence_type' => $evidenceType,
                'observed_value' => ['summary' => $evidenceType],
                'context_summary' => [],
                'quality' => EvidenceQuality::Valid->value,
                'confidence' => EvidenceConfidence::High->value,
                'validation_reason' => 'Fixture M5-06',
                'validated_at' => $at,
            ]);
            $record->validatedEvidence()->sync([$evidence->id]);
        }

        return $record->fresh(['validatedEvidence', 'activity.learningUnit.module', 'activity.programmingActivity.languageExecutionProfile']);
    }
}
