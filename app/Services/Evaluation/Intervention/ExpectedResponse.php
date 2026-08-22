<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\InterventionResponseClassification;
use App\Enums\ObservedImprovementSignal;

/**
 * Independently authored expectation for an M5-05 observed intervention-response
 * classification (M6-03).
 *
 * INDEPENDENCE CONTRACT: authored literals, never produced by executing
 * InterventionResponseQuery. Uses observational wording only — an expected
 * "observed improvement" signal is never an expected causal effect.
 */
final readonly class ExpectedResponse
{
    /**
     * @param  list<InterventionResponseClassification>  $acceptableClassifications
     */
    public function __construct(
        public InterventionResponseClassification $classification,
        public ObservedImprovementSignal $improvementSignal,
        public bool $observedImprovement,
        public array $acceptableClassifications = [],
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'classification' => $this->classification->value,
            'improvement_signal' => $this->improvementSignal->value,
            'observed_improvement' => $this->observedImprovement,
            'acceptable_classifications' => array_map(fn (InterventionResponseClassification $c): string => $c->value, $this->acceptableClassifications),
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
