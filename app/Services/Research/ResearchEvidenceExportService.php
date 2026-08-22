<?php

namespace App\Services\Research;

use App\Enums\ContextDimension;
use App\Models\AdaptiveIntervention;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\NextLearningAction;
use App\Models\ReassessmentCandidate;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use UnitEnum;

/**
 * Privacy-safe research evidence export (M5-07).
 *
 * Assembles canonical research records from existing M3/M4 source-of-truth
 * and M5-01..M5-06 derived queries. Read-only. Does not reimplement M4/M5 rules,
 * perform statistics/causality, or mutate source data.
 */
final class ResearchEvidenceExportService
{
    public const SCHEMA_VERSION = 'm5-07.v1';

    public function __construct(
        private readonly ResearchEvidenceQuery $researchEvidence,
        private readonly LearningStateTrajectoryQuery $trajectoryQuery,
        private readonly WeakAreaIdentificationQuery $weakAreas,
        private readonly InterventionResponseQuery $interventionResponses,
        private readonly ContextualVariationQuery $contextualVariation,
    ) {}

    /**
     * Export research evidence for a bounded scope.
     *
     * Requires learner and/or course. Unscoped "export everything" is rejected.
     *
     * @return array{
     *     schema_version: string,
     *     generated_at: string,
     *     export_scope: array<string, mixed>,
     *     manifest: array<string, mixed>,
     *     records: list<array<string, mixed>>,
     *     jsonl: string,
     *     csv: string,
     *     analysis_boundary: array<string, bool>
     * }
     */
    public function export(?int $userId = null, ?int $courseId = null): array
    {
        if ($userId === null && $courseId === null) {
            throw new InvalidArgumentException(
                'Research export requires a bounded scope: learner, course, or learner+course.'
            );
        }

        $generatedAt = now()->toIso8601String();
        $states = $this->loadStates($userId, $courseId);
        $records = $this->buildRecords($states, $userId, $courseId);

        $manifest = $this->buildManifest($userId, $courseId, $records, $generatedAt);
        $jsonl = $this->toJsonl($records);
        $csv = $this->toCsv($records);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => $generatedAt,
            'export_scope' => $this->scopeMeta($userId, $courseId),
            'manifest' => $manifest,
            'records' => $records,
            'jsonl' => $jsonl,
            'csv' => $csv,
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @return Collection<int, LearningState>
     */
    private function loadStates(?int $userId, ?int $courseId): Collection
    {
        $query = LearningState::query()
            ->with([
                'validatedEvidence.learningEvent',
                'activity.learningUnit.module.course',
                'activity.programmingActivity.languageExecutionProfile',
                'adaptiveInterventions.nextLearningActions',
                'nextLearningActions',
            ]);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($courseId !== null) {
            $query->whereHas('activity.learningUnit.module', function ($inner) use ($courseId): void {
                $inner->where('course_id', $courseId);
            });
        }

        // Deterministic ordering: course → activity → inferred_at → id
        return $query
            ->get()
            ->sortBy([
                fn (LearningState $state): int => (int) ($state->activity?->learningUnit?->module?->course_id ?? 0),
                fn (LearningState $state): int => (int) $state->activity_id,
                fn (LearningState $state): string => optional($state->inferred_at)?->format('Y-m-d H:i:s.u') ?? '',
                fn (LearningState $state): int => (int) $state->id,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @return list<array<string, mixed>>
     */
    private function buildRecords(Collection $states, ?int $userId, ?int $courseId): array
    {
        if ($states->isEmpty()) {
            return [];
        }

        $trajectoryCache = [];
        $weakCache = [];
        $responseCache = [];
        $contextVariationCache = [];

        $courseIds = $states
            ->map(fn (LearningState $state): ?int => $state->activity?->learningUnit?->module?->course_id)
            ->filter()
            ->unique()
            ->values();

        foreach ($courseIds as $cid) {
            $contextVariationCache[(int) $cid] = $this->compactContextualVariation((int) $cid);
        }

        $reassessmentByUserCourse = $this->loadReassessmentIndex($states);

        $records = [];
        foreach ($states as $state) {
            $cid = (int) ($state->activity?->learningUnit?->module?->course_id ?? 0);
            $uid = (int) $state->user_id;
            $aid = (int) $state->activity_id;

            $trajKey = $uid.'|'.$aid;
            if (! array_key_exists($trajKey, $trajectoryCache)) {
                $trajectoryCache[$trajKey] = $this->compactTrajectory(
                    $this->trajectoryQuery->forLearnerActivity($uid, $aid)
                );
            }

            $weakKey = $uid.'|'.$cid;
            if ($cid > 0 && ! array_key_exists($weakKey, $weakCache)) {
                $weakCache[$weakKey] = $this->weakAreas->forLearnerCourse($uid, $cid);
            }

            $context = $state->activity
                ? $this->sanitizeContext($this->researchEvidence->researchContextForActivity($state->activity, $uid))
                : null;

            $learningAreaKey = $this->learningAreaKeyFromContext($context);
            $weakFinding = null;
            if ($cid > 0 && isset($weakCache[$weakKey])) {
                $weakFinding = collect($weakCache[$weakKey]['findings'] ?? [])
                    ->first(fn (array $finding): bool => ($finding['learning_area_key'] ?? null) === $learningAreaKey);
                $weakFinding = $weakFinding ? $this->compactWeakArea($weakFinding) : null;
            }

            $interventions = $state->adaptiveInterventions->sortBy('id')->values();
            $interventionPayloads = [];
            $responsePayloads = [];
            foreach ($interventions as $intervention) {
                $interventionPayloads[] = $this->compactIntervention($intervention);
                if (! array_key_exists($intervention->id, $responseCache)) {
                    $responseCache[$intervention->id] = $this->compactInterventionResponse(
                        $this->interventionResponses->forIntervention($intervention)
                    );
                }
                $responsePayloads[] = $responseCache[$intervention->id];
            }

            $actions = $state->nextLearningActions->sortBy('id')->values()
                ->map(fn (NextLearningAction $action): array => $this->compactNextAction($action))
                ->all();

            $reassessments = $reassessmentByUserCourse[$uid.'|'.$cid] ?? [];
            $matchedReassessments = array_values(array_filter(
                $reassessments,
                fn (array $row): bool => ($row['learning_area_key'] ?? null) === $learningAreaKey
            ));

            $evidence = $state->validatedEvidence->sortBy('id')->values();
            $events = $evidence
                ->map(fn (ValidatedEvidence $item) => $item->learningEvent)
                ->filter()
                ->unique('id')
                ->sortBy('id')
                ->values();

            $records[] = [
                'schema_version' => self::SCHEMA_VERSION,
                'research_learner_id' => $this->researchEvidence->researchLearnerId($uid),
                'context' => $context,
                'learning_event' => $events->map(fn (LearningEvent $event): array => $this->compactEvent($event))->values()->all(),
                'validated_evidence' => $evidence->map(fn (ValidatedEvidence $item): array => $this->compactEvidence($item))->values()->all(),
                'learning_state' => $this->compactLearningState($state),
                'trajectory' => $trajectoryCache[$trajKey],
                'intervention' => $interventionPayloads === [] ? null : $interventionPayloads,
                'next_learning_action' => $actions === [] ? null : $actions,
                'weak_area' => $weakFinding,
                'reassessment' => $matchedReassessments === [] ? null : $matchedReassessments,
                'intervention_response' => $responsePayloads === [] ? null : $responsePayloads,
                'contextual_variation' => [
                    'context_keys' => $this->contextKeys($context),
                    'course_summaries' => $cid > 0 ? ($contextVariationCache[$cid] ?? null) : null,
                ],
                'provenance' => [
                    'learning_state_id' => $state->id,
                    'activity_id' => $aid,
                    'course_id' => $cid > 0 ? $cid : null,
                    'validated_evidence_ids' => $evidence->pluck('id')->values()->all(),
                    'learning_event_ids' => $events->pluck('id')->values()->all(),
                    'adaptive_intervention_ids' => $interventions->pluck('id')->values()->all(),
                    'next_learning_action_ids' => $state->nextLearningActions->pluck('id')->sort()->values()->all(),
                    'reassessment_candidate_ids' => array_values(array_filter(array_map(
                        fn (array $row): ?int => $row['reassessment_candidate_id'] ?? null,
                        $matchedReassessments
                    ))),
                ],
                'privacy' => [
                    'includes_learner_name' => false,
                    'includes_email' => false,
                    'includes_phone' => false,
                    'includes_authentication_id' => false,
                    'includes_ip_or_user_agent' => false,
                    'uses_research_learner_id' => true,
                ],
            ];
        }

        return $records;
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadReassessmentIndex(Collection $states): array
    {
        $pairs = $states
            ->map(function (LearningState $state): ?array {
                $courseId = $state->activity?->learningUnit?->module?->course_id;
                if ($courseId === null) {
                    return null;
                }

                return [(int) $state->user_id, (int) $courseId];
            })
            ->filter()
            ->unique(fn (array $pair): string => $pair[0].'|'.$pair[1])
            ->values();

        $index = [];
        foreach ($pairs as [$uid, $cid]) {
            $candidates = ReassessmentCandidate::query()
                ->where('user_id', $uid)
                ->where('course_id', $cid)
                ->orderBy('id')
                ->get();

            $index[$uid.'|'.$cid] = $candidates
                ->map(fn (ReassessmentCandidate $candidate): array => $this->compactReassessment($candidate))
                ->all();
        }

        return $index;
    }

    /**
     * @return array<string, mixed>
     */
    private function compactContextualVariation(int $courseId): array
    {
        $summaries = [];
        foreach (ContextDimension::cases() as $dimension) {
            $result = $this->contextualVariation->forCourse($courseId, $dimension);
            $summaries[$dimension->value] = [
                'dimension' => $dimension->value,
                'comparable_context_count' => $result['variation_summary']['comparable_context_count'] ?? 0,
                'observed_variation' => (bool) ($result['variation_summary']['observed_variation'] ?? false),
                'claims_context_caused_outcome' => false,
                'contexts' => array_map(function (array $bucket): array {
                    return [
                        'context_key' => $bucket['context_key'] ?? null,
                        'context_label' => $bucket['context_label'] ?? null,
                        'learner_count' => $bucket['learner_count'] ?? 0,
                        'observation_count' => $bucket['observation_count'] ?? 0,
                        'state_distribution' => $bucket['state_distribution'] ?? [],
                        'evidence_sufficiency' => $bucket['evidence_sufficiency'] ?? null,
                    ];
                }, $result['contexts'] ?? []),
            ];
        }

        return $summaries;
    }

    /**
     * @param  array<string, mixed>  $trajectory
     * @return array<string, mixed>
     */
    private function compactTrajectory(array $trajectory): array
    {
        return [
            'sequence' => $trajectory['sequence'] ?? [],
            'transitions' => array_map(function (array $transition): array {
                return [
                    'from_state' => $transition['from_state'] ?? null,
                    'to_state' => $transition['to_state'] ?? null,
                    'transition_type' => $transition['transition_type'] ?? null,
                    'from_learning_state_id' => $transition['from_learning_state_id'] ?? null,
                    'to_learning_state_id' => $transition['to_learning_state_id'] ?? null,
                    'claims_causal_improvement' => false,
                ];
            }, $trajectory['transitions'] ?? []),
            'claims_causal_improvement' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private function compactWeakArea(array $finding): array
    {
        return [
            'learning_area_key' => $finding['learning_area_key'] ?? null,
            'learning_area_label' => $finding['learning_area_label'] ?? null,
            'learning_area_representation' => $finding['learning_area_representation'] ?? null,
            'classification' => $finding['classification'] ?? null,
            'is_weak_area' => (bool) ($finding['is_weak_area'] ?? false),
            'supporting_evidence_ids' => $finding['supporting_evidence_ids'] ?? [],
            'supporting_learning_state_ids' => $finding['supporting_learning_state_ids'] ?? [],
            'activity_ids' => $finding['activity_ids'] ?? [],
            'bloom_demand_context' => $finding['bloom_demand_context'] ?? [],
            'dave_demand_context' => $finding['dave_demand_context'] ?? [],
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'detection_rule' => $finding['detection_rule'] ?? null,
            'claims_psychological_diagnosis' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactIntervention(AdaptiveIntervention $intervention): array
    {
        return [
            'adaptive_intervention_id' => $intervention->id,
            'intervention_type' => $this->enumValue($intervention->intervention_type),
            'selection_rule' => $intervention->selection_rule,
            'is_remedial' => (bool) $intervention->is_remedial,
            'is_strong' => (bool) $intervention->is_strong,
            'learning_state_id' => $intervention->learning_state_id,
            'activity_id' => $intervention->activity_id,
            'available_at' => optional($intervention->created_at)?->toIso8601String(),
            'timestamp_semantics' => 'adaptive_interventions.created_at (availability/cut; no separate delivered_at)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactNextAction(NextLearningAction $action): array
    {
        return [
            'next_learning_action_id' => $action->id,
            'action' => $this->enumValue($action->action),
            'decision_rule' => $action->decision_rule,
            'retry_outcome' => $action->retry_outcome,
            'learning_state_id' => $action->learning_state_id,
            'adaptive_intervention_id' => $action->adaptive_intervention_id,
            'decided_at' => optional($action->decided_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactReassessment(ReassessmentCandidate $candidate): array
    {
        return [
            'reassessment_candidate_id' => $candidate->id,
            'candidate_key' => $candidate->candidate_key,
            'status' => $this->enumValue($candidate->status),
            'learning_area_key' => $candidate->learning_area_key,
            'concept' => $candidate->concept,
            'bloom_demand' => $this->enumValue($candidate->bloom_demand),
            'dave_demand' => $this->enumValue($candidate->dave_demand),
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'generator_identity' => $candidate->generator_identity,
            'generator_model' => $candidate->generator_model,
            'generated_at' => optional($candidate->generated_at)?->toIso8601String(),
            'validated_at' => optional($candidate->validated_at)?->toIso8601String(),
            'delivers_to_learner' => false,
            'claims_effectiveness' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    private function compactInterventionResponse(array $analysis): array
    {
        return [
            'adaptive_intervention_id' => $analysis['intervention_context']['adaptive_intervention_id'] ?? null,
            'response_classification' => $analysis['research_interpretation']['response_classification'] ?? null,
            'observed_improvement_signal' => $analysis['research_interpretation']['observed_improvement_signal'] ?? null,
            'observed_improvement' => (bool) ($analysis['research_interpretation']['observed_improvement'] ?? false),
            'before_state' => $analysis['observed_outcome']['before_state'] ?? null,
            'after_state' => $analysis['observed_outcome']['after_state'] ?? null,
            'comparison_rule' => $analysis['research_interpretation']['comparison_rule'] ?? null,
            'claims_causal_effectiveness' => false,
            'claims_intervention_caused_improvement' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactLearningState(LearningState $state): array
    {
        return [
            'learning_state_id' => $state->id,
            'activity_id' => $state->activity_id,
            'state' => $this->enumValue($state->state),
            'state_confidence' => $this->enumValue($state->state_confidence),
            'inference_rule' => $state->inference_rule,
            'cognitive_indicator' => $state->cognitive_indicator,
            'psychomotor_indicator' => $state->psychomotor_indicator,
            'behavioral_indicators' => $state->behavioral_indicators,
            'bloom_demand' => $this->enumValue($state->bloom_demand),
            'dave_demand' => $this->enumValue($state->dave_demand),
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'inferred_at' => optional($state->inferred_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactEvent(LearningEvent $event): array
    {
        return [
            'learning_event_id' => $event->id,
            'event_type' => $event->event_type,
            'activity_id' => $event->activity_id,
            'course_id' => $event->course_id,
            'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
            'session_id' => $event->session_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compactEvidence(ValidatedEvidence $evidence): array
    {
        return [
            'validated_evidence_id' => $evidence->id,
            'learning_event_id' => $evidence->learning_event_id,
            'activity_id' => $evidence->activity_id,
            'evidence_category' => $this->enumValue($evidence->evidence_category),
            'evidence_type' => $evidence->evidence_type,
            'quality' => $this->enumValue($evidence->quality),
            'confidence' => $this->enumValue($evidence->confidence),
            'validated_at' => optional($evidence->validated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        unset($context['learner_id']);

        return [
            'course_id' => $context['course_id'] ?? null,
            'course_title' => $context['course_title'] ?? null,
            'module_id' => $context['module_id'] ?? null,
            'module_title' => $context['module_title'] ?? null,
            'learning_unit_id' => $context['learning_unit_id'] ?? null,
            'learning_unit_title' => $context['learning_unit_title'] ?? null,
            'activity_id' => $context['activity_id'] ?? null,
            'activity_title' => $context['activity_title'] ?? null,
            'activity_type' => $context['activity_type'] ?? null,
            'programming_language' => $context['programming_language'] ?? null,
            'programming_language_display' => $context['programming_language_display'] ?? null,
            'bloom_demand' => $context['bloom_demand'] ?? null,
            'dave_demand' => $context['dave_demand'] ?? null,
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'concept' => $context['concept'] ?? null,
            'learning_objective' => $context['learning_objective'] ?? null,
            'difficulty' => $context['difficulty'] ?? null,
            'campus' => null,
            'institution' => null,
            'cohort' => null,
            'session_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function learningAreaKeyFromContext(?array $context): ?string
    {
        if ($context === null) {
            return null;
        }

        $concept = trim((string) ($context['concept'] ?? ''));
        if ($concept !== '') {
            return 'concept:'.mb_strtolower($concept);
        }

        if (isset($context['learning_unit_id'])) {
            return 'learning_unit:'.$context['learning_unit_id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    private function contextKeys(?array $context): array
    {
        if ($context === null) {
            return [];
        }

        return [
            'course' => isset($context['course_id']) ? 'course:'.$context['course_id'] : null,
            'module' => isset($context['module_id']) ? 'course:'.$context['course_id'].'|module:'.$context['module_id'] : null,
            'learning_unit' => isset($context['learning_unit_id']) ? 'course:'.$context['course_id'].'|learning_unit:'.$context['learning_unit_id'] : null,
            'activity' => isset($context['activity_id']) ? 'course:'.$context['course_id'].'|activity:'.$context['activity_id'] : null,
            'programming_language' => isset($context['programming_language'])
                ? 'course:'.$context['course_id'].'|language:'.$context['programming_language']
                : null,
            'bloom_task_demand' => isset($context['bloom_demand'])
                ? 'course:'.$context['course_id'].'|bloom:'.$context['bloom_demand']
                : null,
            'dave_task_demand' => isset($context['dave_demand'])
                ? 'course:'.$context['course_id'].'|dave:'.$context['dave_demand']
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function buildManifest(?int $userId, ?int $courseId, array $records, string $generatedAt): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => $generatedAt,
            'export_scope' => $this->scopeMeta($userId, $courseId),
            'record_count' => count($records),
            'format' => [
                'primary' => 'jsonl',
                'secondary' => 'csv',
                'note' => 'CSV is a flattened projection of the same canonical records.',
            ],
            'ordering_rule' => 'course_id ASC, activity_id ASC, inferred_at ASC, learning_state_id ASC',
            'context_dimensions_included' => array_map(
                fn (ContextDimension $dimension): string => $dimension->value,
                ContextDimension::cases(),
            ),
            'source_layers' => [
                'M3/M4 source-of-truth',
                'M5-01 ResearchEvidenceQuery',
                'M5-02 LearningStateTrajectoryQuery',
                'M5-03 WeakAreaIdentificationQuery',
                'M5-04 ReassessmentCandidate',
                'M5-05 InterventionResponseQuery',
                'M5-06 ContextualVariationQuery',
            ],
            'privacy_policy_summary' => [
                'uses_research_learner_id' => true,
                'excludes_name_email_phone' => true,
                'excludes_authentication_identifiers' => true,
                'excludes_ip_and_user_agent' => true,
            ],
            'provenance_policy' => 'Each record references source LearningState/ValidatedEvidence/LearningEvent/Intervention/NextAction/Reassessment IDs without fabricating identifiers.',
            'bloom_dave_policy' => 'Exported only as task_demand, never as learner capability.',
            'timestamp_limitations' => [
                'intervention_delivery_timestamp' => 'not stored; created_at used as availability/cut timestamp',
                'session_id' => 'rarely populated',
            ],
            'data_gaps' => [
                'campus' => true,
                'institution' => true,
                'cohort' => true,
            ],
            'claims_causal_or_statistical_analysis' => false,
            'mutates_source_data' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeMeta(?int $userId, ?int $courseId): array
    {
        return [
            'type' => match (true) {
                $userId !== null && $courseId !== null => 'learner_course',
                $userId !== null => 'learner',
                $courseId !== null => 'course',
                default => 'invalid',
            },
            'research_learner_id' => $userId !== null
                ? $this->researchEvidence->researchLearnerId($userId)
                : null,
            'course_id' => $courseId,
            // Explicitly omit raw user_id from export scope metadata for privacy.
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function toJsonl(array $records): string
    {
        if ($records === []) {
            return '';
        }

        return implode("\n", array_map(
            static fn (array $record): string => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $records,
        ))."\n";
    }

    /**
     * Flattened CSV projection from the same canonical records.
     *
     * @param  list<array<string, mixed>>  $records
     */
    private function toCsv(array $records): string
    {
        $headers = [
            'schema_version',
            'research_learner_id',
            'course_id',
            'activity_id',
            'learning_state_id',
            'state',
            'inferred_at',
            'bloom_demand',
            'dave_demand',
            'bloom_semantics',
            'dave_semantics',
            'weak_area_classification',
            'validated_evidence_ids',
            'learning_event_ids',
            'adaptive_intervention_ids',
            'next_learning_action_ids',
        ];

        $lines = [implode(',', $headers)];
        foreach ($records as $record) {
            $state = $record['learning_state'] ?? [];
            $context = $record['context'] ?? [];
            $provenance = $record['provenance'] ?? [];
            $row = [
                $record['schema_version'] ?? '',
                $record['research_learner_id'] ?? '',
                $context['course_id'] ?? '',
                $context['activity_id'] ?? '',
                $state['learning_state_id'] ?? '',
                $state['state'] ?? '',
                $state['inferred_at'] ?? '',
                $state['bloom_demand'] ?? '',
                $state['dave_demand'] ?? '',
                'task_demand',
                'task_demand',
                $record['weak_area']['classification'] ?? '',
                implode('|', $provenance['validated_evidence_ids'] ?? []),
                implode('|', $provenance['learning_event_ids'] ?? []),
                implode('|', $provenance['adaptive_intervention_ids'] ?? []),
                implode('|', $provenance['next_learning_action_ids'] ?? []),
            ];
            $lines[] = implode(',', array_map([$this, 'csvEscape'], $row));
        }

        return implode("\n", $lines).(count($lines) > 0 ? "\n" : '');
    }

    private function csvEscape(mixed $value): string
    {
        $string = (string) $value;
        if (str_contains($string, ',') || str_contains($string, '"') || str_contains($string, "\n")) {
            return '"'.str_replace('"', '""', $string).'"';
        }

        return $string;
    }

    /**
     * @return array<string, bool>
     */
    private function analysisBoundary(): array
    {
        return [
            'exports_research_evidence' => true,
            'mutates_source_data' => false,
            'includes_pii' => false,
            'performs_statistical_analysis' => false,
            'performs_causal_inference' => false,
            'generates_research_paper' => false,
            'implements_m6' => false,
            'uses_ml_or_llm_for_export' => false,
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $value->value;
        }

        return (string) $value;
    }
}
