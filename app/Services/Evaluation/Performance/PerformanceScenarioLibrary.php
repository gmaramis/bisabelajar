<?php

namespace App\Services\Evaluation\Performance;

/**
 * Independently authored library of synthetic M6-06 performance/reliability
 * scenarios.
 *
 * INDEPENDENCE BOUNDARY: depends only on the evaluation value objects; it does not
 * reference or execute any production component. Objective reliability behaviors
 * (determinism, timeout/failure handling, AI-provider abstraction) are asserted as
 * PASS/FAIL; raw latency/query/memory measurements are reported as REVIEW because
 * the specification forbids inventing thresholds without an evidence-based baseline.
 */
final class PerformanceScenarioLibrary
{
    /**
     * @return list<PerformanceScenario>
     */
    public function all(): array
    {
        $scenarios = [
            new PerformanceScenario(
                'PERF-DETERMINISM-INFERENCE-001', 'determinism', 'inference',
                'Learning State inference is deterministic on re-run.',
                null,
                new ExpectedPerformance(expectDeterministic: true, rationale: 'Deterministic component must yield identical output for identical evidence.'),
            ),
            new PerformanceScenario(
                'PERF-DETERMINISM-CLOSED-LOOP-001', 'determinism', 'closed_loop',
                'Closed-loop orchestration produces a stable cycle_id on re-run.',
                null,
                new ExpectedPerformance(expectDeterministic: true, rationale: 'Idempotent orchestration must reproduce the same cycle identity.'),
            ),
            new PerformanceScenario(
                'PERF-TIMEOUT-HANDLING-001', 'failure_handling', 'reassessment',
                'AI generation timeout is handled gracefully without mutating source of truth.',
                'timeout',
                new ExpectedPerformance(
                    expectGracefulFailure: true, expectedFailureStatus: 'generation_failed',
                    expectSourceOfTruthUnchanged: true,
                    rationale: 'A timeout must degrade to generation_failed and leave durable records unchanged.',
                ),
            ),
            new PerformanceScenario(
                'PERF-FAILURE-HANDLING-001', 'failure_handling', 'reassessment',
                'AI provider unavailability is handled gracefully.',
                'unavailable',
                new ExpectedPerformance(
                    expectGracefulFailure: true, expectedFailureStatus: 'generation_failed',
                    expectSourceOfTruthUnchanged: true,
                    rationale: 'An unavailable AI provider must degrade to generation_failed with no source-of-truth mutation.',
                ),
            ),
            new PerformanceScenario(
                'PERF-AI-ABSTRACTION-001', 'ai_abstraction', 'reassessment',
                'A custom AI generator is used via the contract while AI remains a non-decision-maker.',
                'custom',
                new ExpectedPerformance(
                    expectAiAbstractionHonored: true, expectedGeneratorIdentity: 'm6_06_custom_generator',
                    expectAiNotDecisionMaker: true,
                    rationale: 'The generator abstraction must be swappable, and AI must not be the final decision-maker.',
                ),
            ),
            new PerformanceScenario(
                'PERF-MEASUREMENT-INFERENCE-001', 'measurement', 'inference',
                'Measure Learning State inference latency/query/memory (no invented threshold).',
                null,
                new ExpectedPerformance(measurementOnly: true, rationale: 'Latency/query/memory are measured and reported; a project baseline (human judgment) is required.'),
            ),
            new PerformanceScenario(
                'PERF-MEASUREMENT-EXPORT-001', 'measurement', 'export',
                'Measure research evidence export latency/query (no invented threshold).',
                null,
                new ExpectedPerformance(measurementOnly: true, rationale: 'Export latency/query are measured and reported; a project baseline is required.'),
            ),
            new PerformanceScenario(
                'PERF-DIVERGENCE-FAIL-001', 'failure_handling', 'reassessment',
                'A timeout occurs, but the authored expectation asserts a validated status.',
                'timeout',
                new ExpectedPerformance(
                    expectGracefulFailure: true, expectedFailureStatus: 'validated',
                    rationale: 'Intentionally divergent failure-status expectation used to prove FAIL detection.',
                ),
            ),
        ];

        usort($scenarios, fn (PerformanceScenario $a, PerformanceScenario $b): int => strcmp($a->scenarioId, $b->scenarioId));

        return $scenarios;
    }
}
