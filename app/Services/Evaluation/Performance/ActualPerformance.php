<?php

namespace App\Services\Evaluation\Performance;

/**
 * Actual measured performance/reliability outcome captured from a NEXUS component
 * (M6-06).
 *
 * Captured as detached, privacy-safe scalar data BEFORE the evaluation transaction
 * is rolled back. Measurements are environment-dependent observations, not
 * pass/fail thresholds. The learner appears only as a pseudonymous learner_ref.
 */
final readonly class ActualPerformance
{
    public function __construct(
        public string $learnerRef,
        public string $operation,
        public float $elapsedMs,
        public int $queryCount,
        public int $memoryDeltaKb,
        public int $sampleSize,
        public ?bool $deterministic,
        public ?string $failureStatus,
        public ?bool $failureHandled,
        public ?bool $sourceOfTruthUnchanged,
        public ?string $aiGeneratorIdentity,
        public ?bool $aiIsDecisionMaker,
        public string $note,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'learner_ref' => $this->learnerRef,
            'operation' => $this->operation,
            'measurements' => [
                'elapsed_ms' => $this->elapsedMs,
                'query_count' => $this->queryCount,
                'memory_delta_kb' => $this->memoryDeltaKb,
                'sample_size' => $this->sampleSize,
            ],
            'deterministic' => $this->deterministic,
            'failure_status' => $this->failureStatus,
            'failure_handled' => $this->failureHandled,
            'source_of_truth_unchanged' => $this->sourceOfTruthUnchanged,
            'ai_generator_identity' => $this->aiGeneratorIdentity,
            'ai_is_decision_maker' => $this->aiIsDecisionMaker,
            'note' => $this->note,
        ];
    }
}
