<?php

namespace App\Services\Evaluation;

use App\Enums\BloomLevel;
use App\Enums\DaveLevel;

/**
 * Deterministic, synthetic evaluation scenario (M6-01).
 *
 * A scenario is a self-contained, reproducible description of a learner situation:
 * an activity blueprint (task demand only), an ordered set of observable learning
 * events, an optional post-intervention retry, and an independently authored
 * ExpectedOutcome. Scenarios use synthetic data only — never real learner data.
 */
final readonly class EvaluationScenario
{
    /**
     * @param  string  $scenarioId  Stable identifier used for ordering and provenance.
     * @param  string  $category  Scenario category (learning_state, intervention, next_action, closed_loop, boundary).
     * @param  list<array{type: string, payload: array<string, mixed>}>  $initialEvents  Ordered observable events for the first pass.
     * @param  list<array{type: string, payload: array<string, mixed>}>  $retryEvents  Ordered observable events recorded before the post-intervention retry pass.
     * @param  'success'|'failure'|null  $retryOutcome  Retry outcome when a post-intervention retry pass is run.
     */
    public function __construct(
        public string $scenarioId,
        public string $category,
        public string $description,
        public string $concept,
        public ?BloomLevel $bloomDemand,
        public ?DaveLevel $daveDemand,
        public array $initialEvents,
        public bool $runRetry,
        public array $retryEvents,
        public ?string $retryOutcome,
        public ExpectedOutcome $expected,
    ) {}

    public function runsRetry(): bool
    {
        return $this->runRetry && $this->retryOutcome !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'category' => $this->category,
            'description' => $this->description,
            'concept' => $this->concept,
            'bloom_demand' => $this->bloomDemand?->value,
            'dave_demand' => $this->daveDemand?->value,
            'initial_event_types' => array_map(fn (array $e): string => $e['type'], $this->initialEvents),
            'runs_retry' => $this->runsRetry(),
            'retry_event_types' => array_map(fn (array $e): string => $e['type'], $this->retryEvents),
            'retry_outcome' => $this->retryOutcome,
            'expected' => $this->expected->toArray(),
        ];
    }
}
