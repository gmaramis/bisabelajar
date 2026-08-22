<?php

namespace App\Services\Evaluation\Performance;

/**
 * Deterministic, synthetic scenario for evaluating technical performance,
 * reliability, and failure handling of a NEXUS component (M6-06).
 *
 * `kind` selects the evaluation type (determinism, failure_handling,
 * ai_abstraction, measurement). `operation` selects which real component the
 * runner exercises. `generatorMode` optionally injects a custom/failing
 * AI-generator through the production contract. All data is synthetic.
 */
final readonly class PerformanceScenario
{
    /**
     * @param  'determinism'|'failure_handling'|'ai_abstraction'|'measurement'  $kind
     * @param  'inference'|'closed_loop'|'reassessment'|'export'  $operation
     * @param  'timeout'|'unavailable'|'custom'|null  $generatorMode
     */
    public function __construct(
        public string $scenarioId,
        public string $kind,
        public string $operation,
        public string $description,
        public ?string $generatorMode,
        public ExpectedPerformance $expected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'kind' => $this->kind,
            'operation' => $this->operation,
            'description' => $this->description,
            'generator_mode' => $this->generatorMode,
            'expected' => $this->expected->toArray(),
        ];
    }
}
