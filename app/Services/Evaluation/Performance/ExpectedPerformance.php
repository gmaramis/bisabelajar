<?php

namespace App\Services\Evaluation\Performance;

/**
 * Independently authored expected performance/reliability criteria for one M6-06
 * scenario.
 *
 * INDEPENDENCE CONTRACT: authored from the M6-06 specification and objective
 * reliability semantics, never derived from the implementation being measured.
 *
 * Per the specification, raw latency/throughput/memory targets are NOT invented:
 * such scenarios set `measurementOnly = true` and are reported as REVIEW (they
 * require an evidence-based project baseline). Only objective, threshold-free
 * behaviors (determinism, graceful timeout/failure handling, AI-provider
 * abstraction, AI-not-decision-maker) are asserted as PASS/FAIL.
 */
final readonly class ExpectedPerformance
{
    public function __construct(
        public bool $measurementOnly = false,
        public bool $expectDeterministic = false,
        public bool $expectGracefulFailure = false,
        public ?string $expectedFailureStatus = null,
        public bool $expectSourceOfTruthUnchanged = false,
        public bool $expectAiAbstractionHonored = false,
        public bool $expectAiNotDecisionMaker = false,
        public ?string $expectedGeneratorIdentity = null,
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'measurement_only' => $this->measurementOnly,
            'expect_deterministic' => $this->expectDeterministic,
            'expect_graceful_failure' => $this->expectGracefulFailure,
            'expected_failure_status' => $this->expectedFailureStatus,
            'expect_source_of_truth_unchanged' => $this->expectSourceOfTruthUnchanged,
            'expect_ai_abstraction_honored' => $this->expectAiAbstractionHonored,
            'expect_ai_not_decision_maker' => $this->expectAiNotDecisionMaker,
            'expected_generator_identity' => $this->expectedGeneratorIdentity,
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
