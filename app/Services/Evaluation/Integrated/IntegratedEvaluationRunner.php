<?php

namespace App\Services\Evaluation\Integrated;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Enums\ActivityType;
use App\Enums\BloomLevel;
use App\Enums\ContextDimension;
use App\Enums\DaveLevel;
use App\Enums\EvidenceCategory;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;
use App\Exceptions\ReassessmentGenerationException;
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
use App\Services\Evaluation\EvaluationStatus;
use App\Services\Research\AiAssistedReassessmentService;
use App\Services\Research\ContextualVariationQuery;
use App\Services\Research\NexusClosedLoopService;
use App\Services\Research\Reassessment\DeterministicReassessmentCandidateGenerator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Drives the real authoritative end-to-end NEXUS pipeline for one synthetic
 * scenario and captures cross-layer consistency evidence, without persisting
 * anything (M6-07).
 *
 * READ-ONLY / SOURCE-OF-TRUTH PROTECTION: fixtures and every pipeline write happen
 * inside a transaction that is ALWAYS rolled back; the integrated outcome is
 * captured before rollback. Any injected AI-generator override is always restored
 * to the default binding in a finally block. The runner only calls authoritative
 * entry points and never mutates existing records or production rules.
 */
final class IntegratedEvaluationRunner
{
    private const CONCEPT = 'loops';

    public function __construct(
        private readonly NexusClosedLoopService $closedLoop,
        private readonly AiAssistedReassessmentService $reassessmentService,
        private readonly ContextualVariationQuery $contextualVariationQuery,
        private readonly IntegrationComparator $comparator,
    ) {}

    /**
     * @param  list<IntegrationScenario>  $scenarios
     * @return list<IntegratedValidationResult>
     */
    public function runMany(array $scenarios): array
    {
        return array_map(fn (IntegrationScenario $s): IntegratedValidationResult => $this->run($s), $scenarios);
    }

    public function run(IntegrationScenario $scenario): IntegratedValidationResult
    {
        $notes = [];
        $failureHandled = false;

        try {
            $actual = $this->captureActual($scenario);
        } catch (Throwable $e) {
            $failureHandled = true;
            $actual = $this->errorOutcome($scenario, $e);
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

        return new IntegratedValidationResult(
            scenarioId: $scenario->scenarioId,
            path: $scenario->path,
            status: $status,
            expected: $scenario->expected,
            actual: $actual,
            differences: array_values($differences),
            dimensions: $dimensions,
            constraintCheck: $constraintCheck,
            notes: $notes,
        );
    }

    private function captureActual(IntegrationScenario $scenario): ActualIntegration
    {
        DB::beginTransaction();

        try {
            [$student, $course, $activity] = $this->fixtures($scenario->path === 'privacy_provenance');

            foreach ($scenario->initialEvents as $type) {
                $this->recordEvent($type, $student->id, $course->id, $activity->id);
            }

            $first = $this->closedLoop->processLearnerActivity($student->id, $activity->id);
            $firstAgain = $this->closedLoop->processLearnerActivity($student->id, $activity->id);
            $deterministic = $first['cycle_id'] === $firstAgain['cycle_id'];

            $terminal = $first;
            $retryConsumesSame = null;

            if ($scenario->runsRetry() && $first['intervention'] !== null) {
                foreach ($scenario->retryEvents as $type) {
                    $this->recordEvent($type, $student->id, $course->id, $activity->id);
                }
                $terminal = $this->closedLoop->processAfterRetry($student->id, $activity->id, $first['intervention'], (string) $scenario->retryOutcome);
                $retryConsumesSame = $terminal['next_action']->adaptive_intervention_id === $first['intervention']->id;
            }

            $reassessment = $scenario->runReassessment
                ? $this->runReassessment($student->id, $course->id, $activity->id, $scenario->injectGeneratorFailure)
                : [];

            $contextual = $scenario->runContextualVariation
                ? $this->runContextualVariation($course->id)
                : [];

            return $this->assemble($scenario, $activity, $first, $terminal, $deterministic, $retryConsumesSame, $reassessment, $contextual);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $terminal
     * @param  array<string, mixed>  $reassessment
     * @param  array<string, mixed>  $contextual
     */
    private function assemble(
        IntegrationScenario $scenario,
        Activity $activity,
        array $first,
        array $terminal,
        bool $deterministic,
        ?bool $retryConsumesSame,
        array $reassessment,
        array $contextual,
    ): ActualIntegration {
        /** @var LearningState $state */
        $state = $terminal['learning_state'];
        $terminalIntervention = $terminal['intervention'] ?? null;
        $firstIntervention = $first['intervention'] ?? null;
        $nextAction = $terminal['next_action'];
        $provenance = is_array($terminal['provenance']) ? $terminal['provenance'] : [];

        $stateEvidenceIds = $state->validatedEvidence->pluck('id')->sort()->values()->all();
        $provEvidenceIds = collect($provenance['validated_evidence_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $evidenceMatchesState = $stateEvidenceIds === $provEvidenceIds;

        // Intervention↔state linkage is evaluated on the pass that created the
        // intervention: a remedial intervention is created for a needs_support
        // state, so it must link that state. In a post-retry terminal pass the
        // prior intervention legitimately links the earlier state.
        $firstState = $first['learning_state'];
        $interventionLinksState = $firstIntervention === null
            ? null
            : ($firstIntervention->learning_state_id === $firstState->id
                && $firstIntervention->target_state === $firstState->state);

        // Next-action linkage is evaluated on the terminal pass: it must link the
        // terminal state and, when an intervention is in play, the same intervention.
        $nextActionLinksState = $nextAction->learning_state_id === $state->id
            && ($terminalIntervention === null || $nextAction->adaptive_intervention_id === $terminalIntervention->id);

        $evidencePresent = $stateEvidenceIds !== [];
        $provenanceComplete = ($provenance['learning_state_id'] ?? null) === $state->id
            && ($provenance['next_learning_action_id'] ?? null) === $nextAction->id
            && (! $evidencePresent || ($provenance['learning_event_ids'] ?? []) !== []);

        $taskDemandConsistent = $this->taskDemandConsistent($activity, $state, $terminalIntervention, $nextAction);

        $claimsCausal = (bool) ($provenance['ml_or_llm_orchestration'] ?? false)
            || (bool) ($provenance['longitudinal_analysis'] ?? false)
            || (bool) ($reassessment['claims_effectiveness'] ?? false)
            || (bool) ($contextual['claims_causal'] ?? false);

        return new ActualIntegration(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            path: $scenario->path,
            terminalState: $state->state->value,
            terminalNextAction: $nextAction->action->value,
            interventionPresent: $firstIntervention !== null || $terminalIntervention !== null,
            retryRan: $scenario->runsRetry(),
            retryConsumesSameIntervention: $retryConsumesSame,
            evidenceMatchesState: $evidenceMatchesState,
            interventionLinksState: $interventionLinksState,
            nextActionLinksState: $nextActionLinksState,
            provenanceComplete: $provenanceComplete,
            taskDemandConsistent: $taskDemandConsistent,
            claimsCausal: $claimsCausal,
            deterministic: $deterministic,
            provenance: [
                'cycle_id' => $terminal['cycle_id'] ?? null,
                'learning_event_ids' => array_values($provenance['learning_event_ids'] ?? []),
                'validated_evidence_ids' => $provEvidenceIds,
                'learning_state_id' => $state->id,
                'adaptive_intervention_id' => $terminalIntervention?->id,
                'next_learning_action_id' => $nextAction->id,
            ],
            reassessment: $reassessment,
            contextualVariation: $contextual,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function runReassessment(int $userId, int $courseId, int $activityId, bool $injectFailure): array
    {
        $this->seedPersistentWeakArea($userId, $courseId, $activityId);

        $stateIds = LearningState::query()->where('user_id', $userId)->pluck('id')->all();
        $evidenceIds = ValidatedEvidence::query()->where('user_id', $userId)->pluck('id')->all();

        $countsBefore = [
            'events' => LearningEvent::query()->where('user_id', $userId)->count(),
            'evidence' => ValidatedEvidence::query()->where('user_id', $userId)->count(),
            'states' => LearningState::query()->where('user_id', $userId)->count(),
        ];

        if ($injectFailure) {
            $this->bindGenerator($this->failingGenerator());
        }

        try {
            $service = $injectFailure ? app(AiAssistedReassessmentService::class) : $this->reassessmentService;
            $result = $service->createCandidateForLearningArea($userId, $courseId, 'concept:'.self::CONCEPT);
        } finally {
            if ($injectFailure) {
                $this->restoreGenerator();
            }
        }

        $countsAfter = [
            'events' => LearningEvent::query()->where('user_id', $userId)->count(),
            'evidence' => ValidatedEvidence::query()->where('user_id', $userId)->count(),
            'states' => LearningState::query()->where('user_id', $userId)->count(),
        ];

        $provenance = is_array($result['provenance'] ?? null) ? $result['provenance'] : [];
        $provStateIds = array_map('intval', $provenance['learning_state_ids'] ?? []);
        $provEvidenceIds = array_map('intval', $provenance['validated_evidence_ids'] ?? []);

        $provenanceConsistent = $provStateIds !== []
            && $provEvidenceIds !== []
            && array_intersect($provStateIds, $stateIds) === $provStateIds
            && array_intersect($provEvidenceIds, $evidenceIds) === $provEvidenceIds;

        return [
            'eligible' => (bool) ($result['eligible'] ?? false),
            'status' => $result['status'] ?? null,
            'provenance_consistent' => $provenanceConsistent,
            'source_of_truth_unchanged' => $countsBefore === $countsAfter,
            'upstream_intact' => $countsBefore === $countsAfter,
            'claims_effectiveness' => (bool) ($result['claims_effectiveness'] ?? false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runContextualVariation(int $courseId): array
    {
        $result = $this->contextualVariationQuery->forCourse($courseId, ContextDimension::BloomTaskDemand);
        $summary = is_array($result['variation_summary'] ?? null) ? $result['variation_summary'] : [];
        $contexts = is_array($result['contexts'] ?? null) ? $result['contexts'] : [];

        return [
            'contexts_count' => count($contexts),
            'has_explanation' => trim((string) ($summary['explanation'] ?? '')) !== '',
            'claims_causal' => (bool) ($summary['claims_context_caused_outcome'] ?? false),
        ];
    }

    private function taskDemandConsistent(Activity $activity, LearningState $state, ?AdaptiveIntervention $intervention, NextLearningAction $nextAction): bool
    {
        $bloom = $activity->bloom_demand?->value;

        if ($state->bloom_demand?->value !== $bloom) {
            return false;
        }

        if ($intervention !== null) {
            $meta = is_array($intervention->metadata) ? $intervention->metadata : [];
            if (($meta['bloom_demand'] ?? null) !== $bloom) {
                return false;
            }
        }

        $naMeta = is_array($nextAction->metadata) ? $nextAction->metadata : [];

        return ($naMeta['bloom_demand'] ?? null) === $bloom;
    }

    private function errorOutcome(IntegrationScenario $scenario, Throwable $e): ActualIntegration
    {
        return new ActualIntegration(
            learnerRef: $this->learnerRef($scenario->scenarioId),
            path: $scenario->path,
            terminalState: 'error',
            terminalNextAction: 'error',
            interventionPresent: false,
            retryRan: false,
            retryConsumesSameIntervention: null,
            evidenceMatchesState: false,
            interventionLinksState: null,
            nextActionLinksState: false,
            provenanceComplete: false,
            taskDemandConsistent: false,
            claimsCausal: false,
            deterministic: false,
            provenance: ['error' => $e->getMessage()],
            reassessment: [],
            contextualVariation: [],
        );
    }

    /**
     * @return array{compliant: bool, checks: array<string, bool>, violations: list<string>}
     */
    private function privacyCheck(ActualIntegration $actual): array
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
            'violations' => $safe ? [] : ['integrated output is not privacy-safe (possible PII leak)'],
        ];
    }

    private function recordEvent(string $type, int $userId, int $courseId, int $activityId): void
    {
        LearningEvent::record($type, $userId, $courseId, $activityId, match ($type) {
            'submission_accepted' => ['status' => 'success', 'passes_evaluation' => true],
            'submission_rejected' => ['status' => 'success', 'passes_evaluation' => false],
            default => ['status' => 'success'],
        });
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

    private function failingGenerator(): ReassessmentCandidateGenerator
    {
        return new class implements ReassessmentCandidateGenerator
        {
            public function generate(array $specification): array
            {
                throw new ReassessmentGenerationException('AI provider unavailable', 'ai_unavailable');
            }
        };
    }

    /**
     * @return array{0: User, 1: Course, 2: Activity}
     */
    private function fixtures(bool $sentinel): array
    {
        $tutor = User::factory()->tutor()->create();
        $student = User::factory()->student()->create($sentinel ? [
            'name' => 'Secret Learner Sentinel',
            'email' => 'sentinel.learner@example.com',
        ] : []);
        $course = Course::factory()->for($tutor, 'owner')->published()->public()->create();
        $module = Module::factory()->for($course)->published()->create();
        $unit = LearningUnit::factory()->for($module)->published()->create();
        $activity = Activity::factory()->for($unit, 'learningUnit')->published()->type(ActivityType::CodingExercise)->create([
            'concept' => self::CONCEPT,
            'learning_objective' => 'Write a loop that iterates a list.',
            'bloom_demand' => BloomLevel::Apply,
            'dave_demand' => DaveLevel::Manipulation,
            'difficulty' => 'medium',
        ]);

        return [$student, $course, $activity];
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
                'inference_key' => hash('sha256', uniqid('m6-07-persist-'.$minutes, true)),
                'cognitive_indicator' => 'unresolved_performance_outcome_observed',
                'behavioral_indicators' => ['persistent_attempt_behavior'],
                'bloom_demand' => BloomLevel::Apply,
                'dave_demand' => DaveLevel::Manipulation,
                'explanation' => 'Synthetic needs_support for M6-07.',
                'inference_rule' => 'fixture',
            ]);

            $event = LearningEvent::query()->create([
                'user_id' => $userId,
                'course_id' => $courseId,
                'activity_id' => $activityId,
                'event_type' => 'submission_rejected',
                'payload' => ['synthetic' => true],
                'occurred_at' => now()->subMinutes($minutes),
            ]);
            $evidence = ValidatedEvidence::query()->create([
                'user_id' => $userId,
                'activity_id' => $activityId,
                'learning_event_id' => $event->id,
                'evidence_category' => EvidenceCategory::Performance->value,
                'evidence_type' => 'submission_rejected',
                'observed_value' => ['summary' => 'submission_rejected'],
                'context_summary' => [],
                'quality' => EvidenceQuality::Valid->value,
                'confidence' => EvidenceConfidence::High->value,
                'validation_reason' => 'Synthetic M6-07 evidence.',
                'validated_at' => now()->subMinutes($minutes),
            ]);
            $state->validatedEvidence()->sync([$evidence->id]);
        }
    }

    private function learnerRef(string $scenarioId): string
    {
        return 'learner-'.substr(hash('sha256', 'm6-07|'.$scenarioId), 0, 12);
    }
}
