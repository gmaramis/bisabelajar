<?php

namespace App\Services\Research;

use App\Contracts\Research\ReassessmentCandidateGenerator;
use App\Enums\ReassessmentCandidateStatus;
use App\Enums\WeakAreaClassification;
use App\Exceptions\ReassessmentGenerationException;
use App\Models\Activity;
use App\Models\LearningEvent;
use App\Models\LearningState;
use App\Models\ReassessmentCandidate;
use App\Models\ValidatedEvidence;
use App\Services\Research\Reassessment\ReassessmentCandidateValidator;
use App\Services\Research\Reassessment\ReassessmentSpecificationBuilder;
use UnitEnum;

/**
 * AI-assisted reassessment orchestration (M5-04).
 *
 * Rule-based eligibility + specification + validation.
 * AI/stub only produces candidate wording. Does not deliver to learners
 * or create LearningEvent / ValidatedEvidence / LearningState.
 */
final class AiAssistedReassessmentService
{
    private const ELIGIBLE = [
        WeakAreaClassification::WeakPersistent->value,
        WeakAreaClassification::WeakRepeatedFailure->value,
        WeakAreaClassification::WeakUnresolved->value,
    ];

    public function __construct(
        private readonly WeakAreaIdentificationQuery $weakAreas,
        private readonly ResearchEvidenceQuery $researchEvidence,
        private readonly ReassessmentSpecificationBuilder $specBuilder,
        private readonly ReassessmentCandidateGenerator $generator,
        private readonly ReassessmentCandidateValidator $validator,
    ) {}

    /**
     * Build a reassessment candidate for a learner/course/learning-area from M5-03.
     *
     * @return array<string, mixed>
     */
    public function createCandidateForLearningArea(int $userId, int $courseId, string $learningAreaKey): array
    {
        $weakResult = $this->weakAreas->forLearnerCourse($userId, $courseId);
        $finding = collect($weakResult['findings'])
            ->first(fn (array $row): bool => ($row['learning_area_key'] ?? null) === $learningAreaKey);

        if ($finding === null) {
            return $this->notEligibleResponse(
                $userId,
                $courseId,
                $learningAreaKey,
                ReassessmentCandidateStatus::NotEligibleInsufficientEvidence,
                'no_finding_for_learning_area',
                'No weak-area finding exists for the requested learning area.',
            );
        }

        return $this->createCandidateFromFinding($finding);
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    public function createCandidateFromFinding(array $finding): array
    {
        $userId = (int) $finding['learner_id'];
        $courseId = (int) $finding['course_id'];
        $learningAreaKey = (string) $finding['learning_area_key'];
        $classification = (string) ($finding['classification'] ?? '');

        $eligibility = $this->eligibilityStatus($classification);
        if ($eligibility !== null) {
            return $this->notEligibleResponse(
                $userId,
                $courseId,
                $learningAreaKey,
                $eligibility,
                'weak_area_not_eligible:'.$classification,
                $eligibility === ReassessmentCandidateStatus::NotEligibleRecovered
                    ? 'Learning area is classified as no_current_weakness; reassessment candidate is not generated.'
                    : 'Weak-area classification does not meet reassessment eligibility; candidate is not generated.',
                $finding,
            );
        }

        $sourceSnapshot = $this->sourceActivitySnapshot($finding);
        $specification = $this->specBuilder->build($finding, $sourceSnapshot);
        $aiSafe = $this->specBuilder->aiSafePayload($specification);
        $candidateKey = $this->candidateKey($userId, $courseId, $learningAreaKey, $finding);

        $record = ReassessmentCandidate::query()->updateOrCreate(
            ['candidate_key' => $candidateKey],
            [
                'user_id' => $userId,
                'course_id' => $courseId,
                'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
                'learning_area_key' => $learningAreaKey,
                'learning_area_label' => $finding['learning_area_label'] ?? null,
                'learning_area_representation' => $finding['learning_area_representation'] ?? 'activity_concept',
                'weak_area_classification' => $classification,
                'concept' => $specification['concept'],
                'learning_objective' => $specification['learning_objective'],
                'bloom_demand' => $specification['bloom_demand'],
                'dave_demand' => $specification['dave_demand'],
                'status' => ReassessmentCandidateStatus::EligiblePendingGeneration,
                'specification' => $specification,
                'ai_safe_payload' => $aiSafe,
                'candidate_content' => null,
                'generator_identity' => null,
                'generator_model' => null,
                'generation_metadata' => null,
                'validation_result' => null,
                'validation_errors' => null,
                'failure_reason' => null,
                'generated_at' => null,
                'validated_at' => null,
            ],
        );

        $countsBefore = $this->sourceOfTruthCounts($userId);

        try {
            $generated = $this->generator->generate($aiSafe);
        } catch (ReassessmentGenerationException $exception) {
            $record->fill([
                'status' => ReassessmentCandidateStatus::GenerationFailed,
                'failure_reason' => $exception->getMessage(),
                'generation_metadata' => [
                    'failure_code' => $exception->failureCode,
                ],
            ])->save();

            return $this->responseFromRecord($record->fresh(), $finding, $countsBefore, [
                'eligible' => true,
                'delivered_to_learner' => false,
                'creates_learning_event' => false,
                'creates_validated_evidence' => false,
                'creates_learning_state' => false,
                'claims_improvement' => false,
                'claims_effectiveness' => false,
            ]);
        }

        $record->fill([
            'status' => ReassessmentCandidateStatus::Generated,
            'candidate_content' => $generated,
            'generator_identity' => $generated['generator_identity'] ?? null,
            'generator_model' => $generated['generator_model'] ?? null,
            'generation_metadata' => $generated['metadata'] ?? [],
            'generated_at' => now(),
            'failure_reason' => null,
        ])->save();

        $validation = $this->validator->validate($specification, $generated);

        if ($validation['valid']) {
            $record->fill([
                'status' => ReassessmentCandidateStatus::Validated,
                'validation_result' => $validation,
                'validation_errors' => [],
                'validated_at' => now(),
                'failure_reason' => null,
            ])->save();
        } else {
            $record->fill([
                'status' => ReassessmentCandidateStatus::ValidationFailed,
                'validation_result' => $validation,
                'validation_errors' => $validation['errors'],
                'validated_at' => now(),
                'failure_reason' => 'Candidate failed deterministic validation.',
            ])->save();
        }

        return $this->responseFromRecord($record->fresh(), $finding, $countsBefore, [
            'eligible' => true,
            'delivered_to_learner' => false,
            'creates_learning_event' => false,
            'creates_validated_evidence' => false,
            'creates_learning_state' => false,
            'claims_improvement' => false,
            'claims_effectiveness' => false,
        ]);
    }

    /**
     * Eligibility only (no generation).
     *
     * @param  array<string, mixed>  $finding
     */
    public function isEligible(array $finding): bool
    {
        return $this->eligibilityStatus((string) ($finding['classification'] ?? '')) === null;
    }

    private function eligibilityStatus(string $classification): ?ReassessmentCandidateStatus
    {
        if ($classification === WeakAreaClassification::InsufficientEvidence->value) {
            return ReassessmentCandidateStatus::NotEligibleInsufficientEvidence;
        }

        if ($classification === WeakAreaClassification::NoCurrentWeakness->value) {
            return ReassessmentCandidateStatus::NotEligibleRecovered;
        }

        if (! in_array($classification, self::ELIGIBLE, true)) {
            return ReassessmentCandidateStatus::NotEligibleInsufficientEvidence;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>|null
     */
    private function sourceActivitySnapshot(array $finding): ?array
    {
        $activityIds = $finding['activity_ids'] ?? [];
        if (! is_array($activityIds) || $activityIds === []) {
            return null;
        }

        $activity = Activity::query()
            ->with(['learningUnit.module.course'])
            ->whereIn('id', $activityIds)
            ->orderBy('id')
            ->first();

        if ($activity === null) {
            return null;
        }

        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'concept' => $activity->getConcept(),
            'learning_objective' => $activity->getLearningObjective(),
            'bloom_demand' => $this->enumValue($activity->getBloomDemand()),
            'dave_demand' => $this->enumValue($activity->getDaveDemand()),
            'difficulty' => $activity->getDifficulty(),
            'type' => $this->enumValue($activity->type),
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function candidateKey(int $userId, int $courseId, string $learningAreaKey, array $finding): string
    {
        $evidence = collect($finding['supporting_evidence_ids'] ?? [])->sort()->implode(',');
        $states = collect($finding['supporting_learning_state_ids'] ?? [])->sort()->implode(',');

        return hash(
            'sha256',
            $userId.'|'
            .$courseId.'|'
            .$learningAreaKey.'|'
            .($finding['classification'] ?? '').'|'
            .$evidence.'|'
            .$states
        );
    }

    /**
     * @param  array<string, mixed>|null  $finding
     * @return array<string, mixed>
     */
    private function notEligibleResponse(
        int $userId,
        int $courseId,
        string $learningAreaKey,
        ReassessmentCandidateStatus $status,
        string $rule,
        string $message,
        ?array $finding = null,
    ): array {
        return [
            'eligible' => false,
            'status' => $status->value,
            'decision_rule' => $rule,
            'message' => $message,
            'research_learner_id' => $this->researchEvidence->researchLearnerId($userId),
            'learner_id' => $userId,
            'course_id' => $courseId,
            'learning_area_key' => $learningAreaKey,
            'specification' => $finding ? $this->specBuilder->build($finding, $this->sourceActivitySnapshot($finding)) : null,
            'candidate' => null,
            'delivered_to_learner' => false,
            'creates_learning_event' => false,
            'creates_validated_evidence' => false,
            'creates_learning_state' => false,
            'claims_improvement' => false,
            'claims_effectiveness' => false,
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $finding
     * @param  array<string, int>  $countsBefore
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    private function responseFromRecord(
        ReassessmentCandidate $record,
        ?array $finding,
        array $countsBefore,
        array $flags,
    ): array {
        $countsAfter = $this->sourceOfTruthCounts((int) $record->user_id);

        return [
            'eligible' => $flags['eligible'],
            'status' => $record->status->value,
            'candidate_id' => $record->id,
            'candidate_key' => $record->candidate_key,
            'research_learner_id' => $record->research_learner_id,
            'learner_id' => $record->user_id,
            'course_id' => $record->course_id,
            'learning_area_key' => $record->learning_area_key,
            'specification' => $record->specification,
            'ai_safe_payload' => $record->ai_safe_payload,
            'candidate' => $record->candidate_content,
            'validation_result' => $record->validation_result,
            'validation_errors' => $record->validation_errors,
            'failure_reason' => $record->failure_reason,
            'generator_identity' => $record->generator_identity,
            'generator_model' => $record->generator_model,
            'provenance' => [
                'reassessment_candidate_id' => $record->id,
                'weak_area_classification' => $this->enumValue($record->weak_area_classification),
                'learning_state_ids' => $record->specification['provenance']['learning_state_ids'] ?? [],
                'validated_evidence_ids' => $record->specification['provenance']['validated_evidence_ids'] ?? [],
                'activity_ids' => $record->specification['provenance']['activity_ids'] ?? [],
                'finding_learning_area_key' => $finding['learning_area_key'] ?? $record->learning_area_key,
            ],
            'source_of_truth_unchanged' => $countsBefore === $countsAfter,
            'delivered_to_learner' => false,
            'creates_learning_event' => false,
            'creates_validated_evidence' => false,
            'creates_learning_state' => false,
            'claims_improvement' => false,
            'claims_effectiveness' => false,
            'bloom_semantics' => 'task_demand',
            'dave_semantics' => 'task_demand',
            'analysis_boundary' => $this->analysisBoundary(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function sourceOfTruthCounts(int $userId): array
    {
        return [
            'learning_events' => LearningEvent::query()->where('user_id', $userId)->count(),
            'validated_evidence' => ValidatedEvidence::query()->where('user_id', $userId)->count(),
            'learning_states' => LearningState::query()->where('user_id', $userId)->count(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function analysisBoundary(): array
    {
        return [
            'ai_assisted_candidate_generation' => true,
            'llm_is_final_decision_maker' => false,
            'delivers_to_learner' => false,
            'creates_learning_event' => false,
            'creates_validated_evidence' => false,
            'creates_learning_state' => false,
            'performs_improvement_analysis' => false,
            'performs_contextual_variation_analysis' => false,
            'performs_research_export' => false,
            'claims_psychological_diagnosis' => false,
            'claims_reassessment_effectiveness' => false,
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
