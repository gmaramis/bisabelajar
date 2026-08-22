<?php

namespace App\Services\Evaluation\Performance;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Enums\WeakAreaClassification;
use App\Exceptions\ReassessmentGenerationException;
use App\Models\Activity;
use App\Models\Course;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;
use App\Models\ValidatedEvidence;
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\LearningStateInferenceService;
use App\Services\Research\NexusClosedLoopService;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use App\Services\Research\ResearchEvidenceExportService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Measures technical performance/reliability of the real NEXUS components and
 * evaluates objective reliability behaviors, without persisting anything (M6-06).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION: every fixture and component write happens
 * inside a transaction that is ALWAYS rolled back; measurements and outcomes are
 * captured before rollback. Injected AI-generator overrides are always restored to
 * the default binding. Measurements are observations only — no thresholds are
 * invented, so pure-measurement scenarios resolve to REVIEW.
 */
final class PerformanceEvaluationRunner
{
    public function __construct(
        private readonly LearningStateInferenceService $inference,
        private readonly NexusClosedLoopService $closedLoop,
        private readonly ResearchEvidenceExportService $exportService,
        private readonly PerformanceComparator $comparator,
    ) {}

    /**
     * @param  list<PerformanceScenario>  $scenarios
     * @return list<PerformanceEvaluationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (PerformanceScenario $s): PerformanceEvaluationResult => $this->run($s), $scenarios);
    }

    public function run(PerformanceScenario $scenario): PerformanceEvaluationResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            $actual = $this->captureActual($scenario);
        } catch (Throwable $e) {
            $failureHandled = true;
            $actual = $this->emptyActual($scenario, 'execution error: '.$e->getMessage());
            $notes[] = 'scenario execution raised an exception and was contained: '.$e->getMessage();
        }

        $comparison = $this->comparator->compare($scenario->expected, $actual);
        $constraintCheck = $this->privacyCheck($actual);

        $status = $comparison['status'];
        $dimensions = $comparison['dimensions'];
        $differences = $comparison['differences'];

        $dimensions['privacy'] = ($constraintCheck['checks']['privacy_safe'] ?? false) ? 'pass' : 'fail';
        $dimensions['failure_transparency'] = $failureHandled ? 'review' : 'pass';

        if (! $constraintCheck['compliant']) {
            $differences = array_merge($differences, array_map(fn (string $v): string => 'constraint: '.$v, $constraintCheck['violations']));
            $status = EvaluationStatus::Fail;
        }

        if ($failureHandled && $status === EvaluationStatus::Pass) {
            $status = EvaluationStatus::Review;
        }

        return new PerformanceEvaluationResult(
            scenarioId: $scenario->scenarioId,
            kind: $scenario->kind,
            operation: $scenario->operation,
            status: $status,
            expected: $scenario->expected,
            actual: $actual,
            differences: array_values($differences),
            dimensions: $dimensions,
            constraintCheck: $constraintCheck,
            notes: $notes,
        );
    }

    private function captureActual(PerformanceScenario $scenario): ActualPerformance
    {
        DB::beginTransaction();

        try {
            return match ($scenario->kind) {
                'determinism' => $scenario->operation === 'closed_loop'
                    ? $this->determinismClosedLoop($scenario)
                    : $this->determinismInference($scenario),
                'failure_handling' => $this->reassessmentFailure($scenario),
                'ai_abstraction' => $this->aiAbstraction($scenario),
                'measurement' => $scenario->operation === 'export'
                    ? $this->measureExport($scenario)
                    : $this->measureInference($scenario),
                default => throw new \InvalidArgumentException('Unknown kind: '.$scenario->kind),
            };
        } finally {
            DB::rollBack();
        }
    }

    private function determinismInference(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        $this->seedNeedsSupportEvidence($student->id, $course->id, $activity->id);

        [$elapsed, $queries, $memory] = $this->measure(function () use ($student, $activity, &$first, &$second): void {
            $first = $this->inference->inferForLearnerActivity($student->id, $activity->id);
            $second = $this->inference->inferForLearnerActivity($student->id, $activity->id);
        });

        $deterministic = $first->explanation === $second->explanation
            && $first->inference_rule === $second->inference_rule
            && $first->state === $second->state
            && $first->id === $second->id;

        return $this->actual($scenario, $elapsed, $queries, $memory, 2, deterministic: $deterministic, note: 'T03 inference re-run consistency.');
    }

    private function determinismClosedLoop(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        LearningEvent::record('submission_accepted', $student->id, $course->id, $activity->id, ['status' => 'success', 'passes_evaluation' => true]);

        [$elapsed, $queries, $memory] = $this->measure(function () use ($student, $activity, &$first, &$second): void {
            $first = $this->closedLoop->processLearnerActivity($student->id, $activity->id);
            $second = $this->closedLoop->processLearnerActivity($student->id, $activity->id);
        });

        $deterministic = $first['cycle_id'] === $second['cycle_id']
            && $first['learning_state']->id === $second['learning_state']->id
            && $first['next_action']->id === $second['next_action']->id;

        return $this->actual($scenario, $elapsed, $queries, $memory, 2, deterministic: $deterministic, note: 'Closed-loop cycle_id re-run consistency.');
    }

    private function reassessmentFailure(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        $finding = $this->syntheticFinding($student->id, $course->id, $activity->id);

        $this->bindGenerator($this->failingGenerator($scenario->generatorMode ?? 'unavailable'));

        try {
            $service = app(AiAssistedReassessmentService::class);
            [$elapsed, $queries, $memory] = $this->measure(function () use ($service, $finding, &$result): void {
                $result = $service->createCandidateFromFinding($finding);
            });
        } finally {
            $this->restoreGenerator();
        }

        return $this->actual(
            $scenario, $elapsed, $queries, $memory, 1,
            failureStatus: $result['status'] ?? null,
            failureHandled: ($result['status'] ?? null) === 'generation_failed',
            sourceOfTruthUnchanged: $result['source_of_truth_unchanged'] ?? true,
            note: 'AI generation failure handling ('.($scenario->generatorMode ?? 'unavailable').').',
        );
    }

    private function aiAbstraction(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);

        $this->bindGenerator($this->customGenerator('m6_06_custom_generator'));

        try {
            $service = app(AiAssistedReassessmentService::class);
            [$elapsed, $queries, $memory] = $this->measure(function () use ($service, $student, $course, &$result): void {
                $result = $service->createCandidateForLearningArea($student->id, $course->id, 'concept:loops');
            });
        } finally {
            $this->restoreGenerator();
        }

        return $this->actual(
            $scenario, $elapsed, $queries, $memory, 1,
            aiGeneratorIdentity: $result['generator_identity'] ?? null,
            aiIsDecisionMaker: (bool) ($result['analysis_boundary']['llm_is_final_decision_maker'] ?? false),
            note: 'AI-provider abstraction via the generator contract; AI is not the decision-maker.',
        );
    }

    private function measureInference(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        $this->seedNeedsSupportEvidence($student->id, $course->id, $activity->id);

        [$elapsed, $queries, $memory] = $this->measure(function () use ($student, $activity): void {
            $this->inference->inferForLearnerActivity($student->id, $activity->id);
        });

        return $this->actual($scenario, $elapsed, $queries, $memory, 1, note: 'Learning State inference latency/query measurement (needs project baseline).');
    }

    private function measureExport(PerformanceScenario $scenario): ActualPerformance
    {
        [$student, $course, $activity] = $this->fixtures();
        $this->seedPersistentWeakArea($student->id, $course->id, $activity->id);

        [$elapsed, $queries, $memory] = $this->measure(function () use ($student, $course): void {
            $this->exportService->export($student->id, $course->id);
        });

        return $this->actual($scenario, $elapsed, $queries, $memory, 1, note: 'Research evidence export latency/query measurement (needs project baseline).');
    }

    // ----- measurement + fixtures --------------------------------------------

    /**
     * @param  callable():void  $operation
     * @return array{0: float, 1: int, 2: int}
     */
    private function measure(callable $operation): array
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $memoryStart = memory_get_usage();
        $start = microtime(true);

        $operation();

        $elapsedMs = (microtime(true) - $start) * 1000;
        $queryCount = count(DB::connection()->getQueryLog());
        $memoryDeltaKb = (int) max(0, (memory_get_usage() - $memoryStart) / 1024);
        DB::connection()->disableQueryLog();

        return [round($elapsedMs, 3), $queryCount, $memoryDeltaKb];
    }

    private function actual(
        PerformanceScenario $scenario,
        float $elapsed,
        int $queries,
        int $memory,
        int $sampleSize,
        ?bool $deterministic = null,
        ?string $failureStatus = null,
        ?bool $failureHandled = null,
        ?bool $sourceOfTruthUnchanged = null,
        ?string $aiGeneratorIdentity = null,
        ?bool $aiIsDecisionMaker = null,
        string $note = '',
    ): ActualPerformance {
        return new ActualPerformance(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            operation: $scenario->operation,
            elapsedMs: $elapsed,
            queryCount: $queries,
            memoryDeltaKb: $memory,
            sampleSize: $sampleSize,
            deterministic: $deterministic,
            failureStatus: $failureStatus,
            failureHandled: $failureHandled,
            sourceOfTruthUnchanged: $sourceOfTruthUnchanged,
            aiGeneratorIdentity: $aiGeneratorIdentity,
            aiIsDecisionMaker: $aiIsDecisionMaker,
            note: $note,
        );
    }

    private function emptyActual(PerformanceScenario $scenario, string $note): ActualPerformance
    {
        return $this->actual($scenario, 0.0, 0, 0, 0, note: $note);
    }

    /**
     * @return array{compliant: bool, checks: array<string, bool>, violations: list<string>}
     */
    private function privacyCheck(ActualPerformance $actual): array
    {
        $serialized = strtolower(json_encode($actual->toArray(), JSON_THROW_ON_ERROR));
        $safe = true;
        foreach (['@', 'email', 'sentinel', 'secret learner', 'password'] as $token) {
            if (str_contains($serialized, $token)) {
                $safe = false;
                break;
            }
        }

        return [
            'compliant' => $safe,
            'checks' => ['privacy_safe' => $safe],
            'violations' => $safe ? [] : ['performance output is not privacy-safe (possible PII leak)'],
        ];
    }

    private function bindGenerator(ReassessmentCandidateGenerator $generator): void
    {
        app()->instance(ReassessmentCandidateGenerator::class, $generator);
    }

    private function restoreGenerator(): void
    {
        app()->forgetInstance(ReassessmentCandidateGenerator::class);
        app()->bind(ReassessmentCandidateGenerator::class, DeterministicReassessmentCandidateGenerator::class);
    }

    private function failingGenerator(string $mode): ReassessmentCandidateGenerator
    {
        $code = $mode === 'timeout' ? 'timeout' : 'ai_unavailable';
        $message = $mode === 'timeout' ? 'AI generation timed out' : 'AI provider unavailable';

        return new class($message, $code) implements ReassessmentCandidateGenerator
        {
            public function __construct(private string $message, private string $code) {}

            public function generate(array $specification): array
            {
                throw new ReassessmentGenerationException($this->message, $this->code);
            }
        };
    }

    private function customGenerator(string $identity): ReassessmentCandidateGenerator
    {
        return new class($identity) implements ReassessmentCandidateGenerator
        {
            public function __construct(private string $identity) {}

            public function generate(array $specification): array
            {
                return [
                    'title' => 'Reassessment for '.($specification['concept'] ?? 'topic'),
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
                    'generator_identity' => $this->identity,
                    'generator_model' => 'm6-06-custom-v1',
                    'metadata' => ['ai_assisted' => true, 'llm_decision_maker' => false],
                ];
            }
        };
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function fixtures(): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create();
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => 'loops',
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
        ]);

        return [$student, $course, $activity];
    }

    private function seedNeedsSupportEvidence(int $userId, int $courseId, int $activityId): void
    {
        $this->seedEvidence($userId, $activityId, 'submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::Medium, $courseId);
        $this->seedEvidence($userId, $activityId, 'repeated_submission_failures', EvidenceCategory::Behavioral, EvidenceQuality::ContextDependent, EvidenceConfidence::Medium, $courseId);
    }

    private function seedEvidence(int $userId, int $activityId, string $type, EvidenceCategory $category, EvidenceQuality $quality, EvidenceConfidence $confidence, int $courseId): ValidatedEvidence
    {
        $event = LearningEvent::query()->create([
            'user_id' => $userId,
            'course_id' => $courseId,
            'activity_id' => $activityId,
            'event_type' => $type === 'repeated_submission_failures' ? 'submission_rejected' : $type,
            'payload' => ['synthetic' => true],
            'occurred_at' => now(),
        ]);

        return ValidatedEvidence::query()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'learning_event_id' => $event->id,
            'evidence_category' => $category->value,
            'evidence_type' => $type,
            'observed_value' => ['summary' => $type],
            'context_summary' => ['task_repetition' => 'new', 'task_difficulty' => 'medium', 'execution_anomaly' => 'none', 'network_environment' => 'unknown'],
            'quality' => $quality->value,
            'confidence' => $confidence->value,
            'validation_reason' => 'Synthetic validated evidence for M6-06 evaluation.',
            'validated_at' => now(),
        ]);
    }

    private function seedPersistentWeakArea(int $userId, int $courseId, int $activityId): void
    {
        foreach ([40, 30, 20] as $minutes) {
            $state = LearningState::factory()->create([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'state' => LearningStateValue::NeedsSupport,
                'state_confidence' => StateConfidence::Medium,
                'inferred_at' => now()->subMinutes($minutes),
                'inference_key' => hash('sha256', uniqid('m6-06-persist-'.$minutes, true)),
                'cognitive_indicator' => 'unresolved_performance_outcome_observed',
                'behavioral_indicators' => ['persistent_attempt_behavior'],
                'bloom_demand' => BloomLevel::Apply,
                'dave_demand' => DaveLevel::Manipulation,
                'explanation' => 'Synthetic needs_support for M6-06.',
                'inference_rule' => 'fixture',
            ]);
            $evidence = $this->seedEvidence($userId, $activityId, 'submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High, $courseId);
            $state->validatedEvidence()->sync([$evidence->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function syntheticFinding(int $userId, int $courseId, int $activityId): array
    {
        $state = LearningState::factory()->create([
            'user_id' => $userId,
            'activity_id' => $activityId,
            'state' => LearningStateValue::NeedsSupport,
            'state_confidence' => StateConfidence::Medium,
            'inferred_at' => now()->subMinutes(10),
            'inference_key' => hash('sha256', uniqid('m6-06-finding', true)),
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
        ]);
        $evidence = $this->seedEvidence($userId, $activityId, 'submission_rejected', EvidenceCategory::Performance, EvidenceQuality::Valid, EvidenceConfidence::High, $courseId);
        $state->validatedEvidence()->sync([$evidence->id]);

        return [
            'research_learner_id' => hash('sha256', 'm6-06-eval'),
            'learner_id' => $userId,
            'course_id' => $courseId,
            'learning_area_key' => 'concept:loops',
            'learning_area_label' => 'loops',
            'learning_area_representation' => 'activity_concept',
            'classification' => WeakAreaClassification::WeakPersistent->value,
            'is_weak_area' => true,
            'supporting_evidence_ids' => [$evidence->id],
            'supporting_learning_state_ids' => [$state->id],
            'activity_ids' => [$activityId],
            'trajectory' => ['sequence' => ['needs_support', 'needs_support'], 'transitions' => []],
            'bloom_demand_context' => ['apply'],
            'dave_demand_context' => ['manipulation'],
            'evidence_quality_summary' => ['valid' => 1],
            'evidence_confidence_summary' => ['high' => 1],
            'detection_rule' => 'fixture',
            'explanation' => 'Synthetic weak-area finding for M6-06 evaluation.',
        ];
    }

    private function learnerRef(string $scenarioId): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-06|'.$scenarioId), 0, 12);
    }
}
