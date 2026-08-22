<?php

namespace App\Services\Research;

use App\Enums\ContextDimension;
use App\Enums\ContextEvidenceSufficiency;
use App\Enums\EvidenceConfidence;
use App\Enums\EvidenceQuality;
use App\Enums\InterventionResponseClassification;
use App\Enums\LearningStateValue;
use App\Enums\ObservedImprovementSignal;
use App\Models\AdaptiveIntervention;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\ValidatedEvidence;
use Illuminate\Support\Collection;

/**
 * Deterministic contextual variation analysis (M5-06).
 *
 * Derived/read-only descriptive comparison across available pedagogical contexts.
 * Does not invent campus/institution/cohort, perform causal inference,
 * calculate p-values, or export research packages.
 */
final class ContextualVariationQuery
{
    /** Minimum observations for a non-limited pattern within one context bucket. */
    private const MIN_OBSERVATIONS_FOR_PATTERN = 3;

    /** Minimum distinct learners for a non-limited pattern. */
    private const MIN_LEARNERS_FOR_PATTERN = 2;

    public function __construct(
        private readonly ResearchEvidenceQuery $researchEvidence,
        private readonly WeakAreaIdentificationQuery $weakAreas,
        private readonly InterventionResponseQuery $interventionResponses,
    ) {}

    /**
     * List context dimensions that the current schema can support.
     *
     * @return array<string, mixed>
     */
    public function availableDimensions(): array
    {
        return [
            'available' => array_map(
                fn (ContextDimension $dimension): string => $dimension->value,
                ContextDimension::cases(),
            ),
            'unavailable' => [
                'campus' => 'not_in_schema',
                'institution' => 'not_in_schema',
                'cohort' => 'not_in_schema',
            ],
            'sparse_or_limited' => [
                'session' => 'learning_events.session_id exists but is rarely populated; not used as a V1 aggregation dimension',
            ],
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
        ];
    }

    /**
     * Analyze contextual variation within one course for a chosen dimension.
     *
     * Course boundaries are preserved: unrelated courses are never merged.
     *
     * @return array<string, mixed>
     */
    public function forCourse(int $courseId, ContextDimension $dimension): array
    {
        $states = LearningState::query()
            ->with([
                'validatedEvidence.learningEvent',
                'activity.learningUnit.module.course',
                'activity.programmingActivity.languageExecutionProfile',
            ])
            ->whereHas('activity.learningUnit.module', function ($query) use ($courseId): void {
                $query->where('course_id', $courseId);
            })
            ->orderBy('inferred_at')
            ->orderBy('id')
            ->get();

        $buckets = $states->groupBy(
            fn (LearningState $state): string => $this->contextKey($dimension, $state) ?? 'unavailable'
        );

        $contexts = $buckets
            ->map(fn (Collection $group, string $key): array => $this->summarizeBucket(
                $courseId,
                $dimension,
                $key,
                $group->values(),
            ))
            ->sortBy('context_key')
            ->values()
            ->all();

        $sessionPopulated = LearningEvent::query()
            ->where('course_id', $courseId)
            ->whereNotNull('session_id')
            ->where('session_id', '!=', '')
            ->count();

        return [
            'scope' => [
                'type' => 'course',
                'course_id' => $courseId,
            ],
            'dimension' => $dimension->value,
            'contexts' => $contexts,
            'variation_summary' => $this->variationSummary($dimension, $contexts),
            'session_sparsity' => [
                'populated_session_event_count' => $sessionPopulated,
                'used_as_dimension' => false,
                'note' => $sessionPopulated === 0
                    ? 'session_id is unpopulated for this course; session is not used as a V1 context dimension.'
                    : 'session_id has some populated rows but remains sparse; session is not used as a V1 aggregation dimension.',
            ],
            'data_gaps' => [
                'campus' => true,
                'institution' => true,
                'cohort' => true,
            ],
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @param  Collection<int, LearningState>  $states
     * @return array<string, mixed>
     */
    private function summarizeBucket(
        int $courseId,
        ContextDimension $dimension,
        string $contextKey,
        Collection $states,
    ): array {
        if ($contextKey === 'unavailable' || $states->isEmpty()) {
            return [
                'context_dimension' => $dimension->value,
                'context_key' => $contextKey,
                'context_label' => 'unavailable',
                'learner_count' => 0,
                'observation_count' => 0,
                'research_learner_ids' => [],
                'state_distribution' => $this->emptyStateDistribution(),
                'weak_area_summary' => [
                    'finding_count' => 0,
                    'weak_area_count' => 0,
                    'classifications' => [],
                ],
                'intervention_summary' => [
                    'intervention_count' => 0,
                    'response_classifications' => [],
                    'improvement_signals' => [],
                ],
                'evidence_quality_summary' => [
                    'valid' => 0,
                    'context_dependent' => 0,
                    'uncertain' => 0,
                ],
                'evidence_confidence_summary' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0,
                ],
                'evidence_sufficiency' => ContextEvidenceSufficiency::InsufficientEvidence->value,
                'explanation' => 'No observations are available for this context bucket.',
                'provenance' => [
                    'learning_state_ids' => [],
                    'validated_evidence_ids' => [],
                    'activity_ids' => [],
                    'adaptive_intervention_ids' => [],
                ],
                'bloom_semantics' => 'task_demand',
                'dave_semantics' => 'task_demand',
                'claims_context_caused_outcome' => false,
            ];
        }

        /** @var LearningState $sample */
        $sample = $states->first();
        $contextMeta = $this->researchEvidence->researchContextForActivity($sample->activity);
        $label = $this->contextLabel($dimension, $sample, $contextMeta);

        $learnerIds = $states->pluck('user_id')->unique()->sort()->values();
        $researchLearnerIds = $learnerIds
            ->map(fn (int $userId): string => $this->researchEvidence->researchLearnerId($userId))
            ->values()
            ->all();

        $stateDistribution = [
            LearningStateValue::NeedsSupport->value => $states->where('state', LearningStateValue::NeedsSupport)->count(),
            LearningStateValue::Progressing->value => $states->where('state', LearningStateValue::Progressing)->count(),
            LearningStateValue::Stable->value => $states->where('state', LearningStateValue::Stable)->count(),
            LearningStateValue::InsufficientEvidence->value => $states->where('state', LearningStateValue::InsufficientEvidence)->count(),
        ];

        $activityIds = $states->pluck('activity_id')->unique()->sort()->values()->all();
        $stateIds = $states->pluck('id')->values()->all();
        $evidenceIds = $states
            ->flatMap(fn (LearningState $state) => $state->validatedEvidence->pluck('id'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $evidenceRows = ValidatedEvidence::query()->whereIn('id', $evidenceIds)->get();
        $qualitySummary = [
            'valid' => $evidenceRows->where('quality', EvidenceQuality::Valid)->count(),
            'context_dependent' => $evidenceRows->where('quality', EvidenceQuality::ContextDependent)->count(),
            'uncertain' => $evidenceRows->where('quality', EvidenceQuality::Uncertain)->count(),
        ];
        $confidenceSummary = [
            'high' => $evidenceRows->where('confidence', EvidenceConfidence::High)->count(),
            'medium' => $evidenceRows->where('confidence', EvidenceConfidence::Medium)->count(),
            'low' => $evidenceRows->where('confidence', EvidenceConfidence::Low)->count(),
        ];

        $weakSummary = $this->weakAreaSummaryForActivities($courseId, $activityIds);
        $interventionSummary = $this->interventionSummaryForActivities($activityIds);

        $observationCount = $states->count();
        $learnerCount = $learnerIds->count();
        $sufficiency = $this->sufficiency($observationCount, $learnerCount);

        return [
            'context_dimension' => $dimension->value,
            'context_key' => $contextKey,
            'context_label' => $label,
            'learner_count' => $learnerCount,
            'observation_count' => $observationCount,
            'unit_note' => 'learner_count counts distinct learners; observation_count counts LearningState records. The same learner may appear in multiple contexts.',
            'research_learner_ids' => $researchLearnerIds,
            'state_distribution' => $stateDistribution,
            'weak_area_summary' => $weakSummary,
            'intervention_summary' => $interventionSummary,
            'evidence_quality_summary' => $qualitySummary,
            'evidence_confidence_summary' => $confidenceSummary,
            'evidence_sufficiency' => $sufficiency->value,
            'explanation' => $this->bucketExplanation($dimension, $label, $sufficiency, $stateDistribution, $observationCount, $learnerCount),
            'provenance' => [
                'learning_state_ids' => $stateIds,
                'validated_evidence_ids' => $evidenceIds,
                'activity_ids' => $activityIds,
                'adaptive_intervention_ids' => $interventionSummary['adaptive_intervention_ids'],
            ],
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'claims_context_caused_outcome' => false,
        ];
    }

    /**
     * @param  list<int>  $activityIds
     * @return array<string, mixed>
     */
    private function weakAreaSummaryForActivities(int $courseId, array $activityIds): array
    {
        if ($activityIds === []) {
            return [
                'finding_count' => 0,
                'weak_area_count' => 0,
                'classifications' => [],
            ];
        }

        // Aggregate weak-area findings across learners in-course, filtered to activities in this bucket.
        $userIds = LearningState::query()
            ->whereIn('activity_id', $activityIds)
            ->distinct()
            ->pluck('user_id');

        $classifications = [];
        $findingCount = 0;
        $weakCount = 0;

        foreach ($userIds as $userId) {
            $result = $this->weakAreas->forLearnerCourse((int) $userId, $courseId);
            foreach ($result['findings'] as $finding) {
                $overlap = array_intersect($finding['activity_ids'] ?? [], $activityIds);
                if ($overlap === []) {
                    continue;
                }
                $findingCount++;
                $class = (string) ($finding['classification'] ?? 'unknown');
                $classifications[$class] = ($classifications[$class] ?? 0) + 1;
                if (! empty($finding['is_weak_area'])) {
                    $weakCount++;
                }
            }
        }

        ksort($classifications);

        return [
            'finding_count' => $findingCount,
            'weak_area_count' => $weakCount,
            'classifications' => $classifications,
        ];
    }

    /**
     * @param  list<int>  $activityIds
     * @return array<string, mixed>
     */
    private function interventionSummaryForActivities(array $activityIds): array
    {
        $interventions = AdaptiveIntervention::query()
            ->whereIn('activity_id', $activityIds)
            ->orderBy('id')
            ->get();

        $responseCounts = [];
        $improvementCounts = [];

        foreach ($interventions as $intervention) {
            $analysis = $this->interventionResponses->forIntervention($intervention);
            $response = (string) ($analysis['research_interpretation']['response_classification'] ?? 'unknown');
            $signal = (string) ($analysis['research_interpretation']['observed_improvement_signal'] ?? 'unknown');
            $responseCounts[$response] = ($responseCounts[$response] ?? 0) + 1;
            $improvementCounts[$signal] = ($improvementCounts[$signal] ?? 0) + 1;
        }

        ksort($responseCounts);
        ksort($improvementCounts);

        return [
            'intervention_count' => $interventions->count(),
            'adaptive_intervention_ids' => $interventions->pluck('id')->values()->all(),
            'response_classifications' => $responseCounts,
            'improvement_signals' => $improvementCounts,
            'positive_response_count' => $responseCounts[InterventionResponseClassification::PositiveResponse->value] ?? 0,
            'observed_improvement_count' => ($improvementCounts[ObservedImprovementSignal::ObservedImprovement->value] ?? 0)
                + ($improvementCounts[ObservedImprovementSignal::StabilizationSignal->value] ?? 0),
            'persistent_difficulty_count' => $responseCounts[InterventionResponseClassification::NegativeOrPersistentDifficulty->value] ?? 0,
        ];
    }

    private function contextKey(ContextDimension $dimension, LearningState $state): ?string
    {
        $activity = $state->activity;
        if ($activity === null) {
            return null;
        }

        $ctx = $this->researchEvidence->researchContextForActivity($activity);

        return match ($dimension) {
            ContextDimension::Course => isset($ctx['course_id']) ? 'course:'.$ctx['course_id'] : null,
            ContextDimension::Module => isset($ctx['module_id']) ? 'course:'.$ctx['course_id'].'|module:'.$ctx['module_id'] : null,
            ContextDimension::LearningUnit => isset($ctx['learning_unit_id']) ? 'course:'.$ctx['course_id'].'|learning_unit:'.$ctx['learning_unit_id'] : null,
            ContextDimension::Activity => 'course:'.$ctx['course_id'].'|activity:'.$activity->id,
            ContextDimension::ProgrammingLanguage => isset($ctx['programming_language'])
                ? 'course:'.$ctx['course_id'].'|language:'.$ctx['programming_language']
                : null,
            ContextDimension::BloomTaskDemand => isset($ctx['bloom_demand'])
                ? 'course:'.$ctx['course_id'].'|bloom:'.$ctx['bloom_demand']
                : null,
            ContextDimension::DaveTaskDemand => isset($ctx['dave_demand'])
                ? 'course:'.$ctx['course_id'].'|dave:'.$ctx['dave_demand']
                : null,
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function contextLabel(ContextDimension $dimension, LearningState $state, array $ctx): string
    {
        return match ($dimension) {
            ContextDimension::Course => (string) ($ctx['course_title'] ?? ('course:'.$ctx['course_id'])),
            ContextDimension::Module => (string) ($ctx['module_title'] ?? ('module:'.$ctx['module_id'])),
            ContextDimension::LearningUnit => (string) ($ctx['learning_unit_title'] ?? ('learning_unit:'.$ctx['learning_unit_id'])),
            ContextDimension::Activity => (string) ($ctx['activity_title'] ?? ('activity:'.$state->activity_id)),
            ContextDimension::ProgrammingLanguage => (string) ($ctx['programming_language_display'] ?? $ctx['programming_language'] ?? 'unavailable'),
            ContextDimension::BloomTaskDemand => 'Bloom task demand: '.($ctx['bloom_demand'] ?? 'unavailable'),
            ContextDimension::DaveTaskDemand => 'Dave task demand: '.($ctx['dave_demand'] ?? 'unavailable'),
        };
    }

    private function sufficiency(int $observations, int $learners): ContextEvidenceSufficiency
    {
        if ($observations === 0) {
            return ContextEvidenceSufficiency::InsufficientEvidence;
        }

        if ($observations < self::MIN_OBSERVATIONS_FOR_PATTERN || $learners < self::MIN_LEARNERS_FOR_PATTERN) {
            return ContextEvidenceSufficiency::LimitedContextEvidence;
        }

        return ContextEvidenceSufficiency::ObservedContextPattern;
    }

    /**
     * @param  array<string, int>  $stateDistribution
     */
    private function bucketExplanation(
        ContextDimension $dimension,
        string $label,
        ContextEvidenceSufficiency $sufficiency,
        array $stateDistribution,
        int $observations,
        int $learners,
    ): string {
        $base = sprintf(
            'Context "%s" (%s) has %d LearningState observation(s) across %d distinct learner(s). State distribution: needs_support=%d, progressing=%d, stable=%d, insufficient_evidence=%d. Evidence sufficiency: %s.',
            $label,
            $dimension->value,
            $observations,
            $learners,
            $stateDistribution[LearningStateValue::NeedsSupport->value] ?? 0,
            $stateDistribution[LearningStateValue::Progressing->value] ?? 0,
            $stateDistribution[LearningStateValue::Stable->value] ?? 0,
            $stateDistribution[LearningStateValue::InsufficientEvidence->value] ?? 0,
            $sufficiency->value,
        );

        return $base.' This is an observed contextual pattern description, not a claim that the context caused the learning outcomes.';
    }

    /**
     * @param  list<array<string, mixed>>  $contexts
     * @return array<string, mixed>
     */
    private function variationSummary(ContextDimension $dimension, array $contexts): array
    {
        $comparable = array_values(array_filter(
            $contexts,
            fn (array $row): bool => ($row['context_key'] ?? '') !== 'unavailable'
                && ($row['observation_count'] ?? 0) > 0,
        ));

        if (count($comparable) < 2) {
            return [
                'has_multiple_contexts' => count($comparable) > 1,
                'comparable_context_count' => count($comparable),
                'observed_variation' => false,
                'explanation' => 'Fewer than two populated context buckets are available for descriptive comparison.',
                'claims_context_caused_outcome' => false,
            ];
        }

        $distributions = array_map(
            fn (array $row): array => $row['state_distribution'] ?? [],
            $comparable,
        );
        $observedDifference = count(array_unique(array_map('serialize', $distributions))) > 1;

        $labels = array_map(fn (array $row): string => (string) $row['context_label'], $comparable);

        return [
            'has_multiple_contexts' => true,
            'comparable_context_count' => count($comparable),
            'observed_variation' => $observedDifference,
            'compared_context_labels' => $labels,
            'explanation' => $observedDifference
                ? sprintf(
                    'Observed state distribution differs across %s contexts (%s). This reports observed contextual variation only and does not claim that any context caused better or worse learning.',
                    $dimension->value,
                    implode(' vs ', $labels),
                )
                : sprintf(
                    'Multiple %s contexts were compared and no difference in state distribution was observed under V1 descriptive rules. Absence of difference is also not a causal conclusion.',
                    $dimension->value,
                ),
            'claims_context_caused_outcome' => false,
            'forbidden_claims_excluded' => [
                'context_caused_improvement',
                'programming_language_caused_difficulty',
                'course_caused_weakness',
                'bloom_level_as_learner_capability',
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyStateDistribution(): array
    {
        return [
            LearningStateValue::NeedsSupport->value => 0,
            LearningStateValue::Progressing->value => 0,
            LearningStateValue::Stable->value => 0,
            LearningStateValue::InsufficientEvidence->value => 0,
        ];
    }

    /**
     * @return array<string, bool|list<string>>
     */
    private function analysisBoundary(): array
    {
        return [
            'describes_contextual_variation' => true,
            'claims_context_caused_outcome' => false,
            'claims_programming_language_caused_difficulty' => false,
            'claims_course_caused_weakness' => false,
            'performs_statistical_significance' => false,
            'calculates_p_values' => false,
            'calculates_effect_sizes' => false,
            'performs_causal_inference' => false,
            'uses_ml_or_llm' => false,
            'performs_research_export' => false,
            'fabricates_campus_institution_cohort' => false,
            'mutates_m3_m4_m5_source_rules' => false,
            'persists_context_analysis_table' => false,
            'bloom_dave_are_task_demands' => true,
        ];
    }
}
