<?php

namespace App\Services\Evaluation\LearningState;

use App\Enums\LearningStateValue;
use App\Enums\StateConfidence;

/**
 * Independently authored expected Learning State outcome for an M6-02 scenario.
 *
 * INDEPENDENCE CONTRACT:
 * Every field is authored by the scenario designer from the scenario's intent and
 * the documented M4-T03 behavior. It is NEVER produced by calling
 * LearningStateInferenceService, NexusClosedLoopService, or any Research service
 * under validation. Optional fields left null are simply not asserted.
 */
final readonly class ExpectedLearningState
{
    /**
     * @param  list<LearningStateValue>  $acceptableStates  States that count as a soft (REVIEW) match when not the primary state.
     * @param  list<StateConfidence>  $acceptableConfidences  Allowed confidences; empty means confidence is not asserted.
     * @param  list<string>  $requiredBehavioralIndicators  Behavioral indicators that must all be present.
     * @param  list<string>  $explanationContains  Substrings the explanation must contain.
     */
    public function __construct(
        public LearningStateValue $state,
        public array $acceptableStates = [],
        public ?string $inferenceRule = null,
        public array $acceptableConfidences = [],
        public ?string $cognitiveIndicator = null,
        public ?string $psychomotorIndicator = null,
        public array $requiredBehavioralIndicators = [],
        public array $explanationContains = [],
        public ?int $expectedUsableCount = null,
        public ?int $expectedUncertainCount = null,
        public ?int $expectedContextDependentCount = null,
        public bool $ambiguous = false,
        public bool $reviewWhenLowConfidence = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'acceptable_states' => array_map(fn (LearningStateValue $s): string => $s->value, $this->acceptableStates),
            'inference_rule' => $this->inferenceRule,
            'acceptable_confidences' => array_map(fn (StateConfidence $c): string => $c->value, $this->acceptableConfidences),
            'cognitive_indicator' => $this->cognitiveIndicator,
            'psychomotor_indicator' => $this->psychomotorIndicator,
            'required_behavioral_indicators' => $this->requiredBehavioralIndicators,
            'explanation_contains' => $this->explanationContains,
            'expected_usable_count' => $this->expectedUsableCount,
            'expected_uncertain_count' => $this->expectedUncertainCount,
            'expected_context_dependent_count' => $this->expectedContextDependentCount,
            'ambiguous' => $this->ambiguous,
            'review_when_low_confidence' => $this->reviewWhenLowConfidence,
            'rationale' => $this->rationale,
        ];
    }
}
