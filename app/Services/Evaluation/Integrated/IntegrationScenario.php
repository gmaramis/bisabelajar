<?php

namespace App\Services\Evaluation\Integrated;

/**
 * Deterministic, synthetic end-to-end scenario for integrated NEXUS validation
 * (M6-07).
 *
 * `path` selects the integrated flow. `initialEvents`/`retryEvents` are ordered
 * observable event types that the runner records through the real M3 entry point;
 * `retryOutcome` drives the closed-loop retry pass. Flags select reassessment,
 * contextual variation, and injected-failure integration boundaries. All data is
 * synthetic.
 */
final readonly class IntegrationScenario
{
    /**
     * @param  list<string>  $initialEvents
     * @param  list<string>  $retryEvents
     * @param  'success'|'failure'|null  $retryOutcome
     */
    public function __construct(
        public string $scenarioId,
        public string $path,
        public string $description,
        public string $concept,
        public array $initialEvents,
        public bool $runRetry,
        public array $retryEvents,
        public ?string $retryOutcome,
        public bool $runReassessment,
        public bool $runContextualVariation,
        public bool $injectGeneratorFailure,
        public ExpectedIntegration $expected,
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
            'path' => $this->path,
            'description' => $this->description,
            'concept' => $this->concept,
            'initial_events' => $this->initialEvents,
            'runs_retry' => $this->runsRetry(),
            'retry_events' => $this->retryEvents,
            'retry_outcome' => $this->retryOutcome,
            'run_reassessment' => $this->runReassessment,
            'run_contextual_variation' => $this->runContextualVariation,
            'inject_generator_failure' => $this->injectGeneratorFailure,
            'expected' => $this->expected->toArray(),
        ];
    }
}
