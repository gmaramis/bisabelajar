<?php

namespace App\Services\Evaluation\CognitiveAffective;

use App\Enums\LearningStateValue;

/**
 * Independently authored expectation for the observable cognitive/psychomotor/
 * behavioral indicators produced by the M4-T03 model (M6-04).
 *
 * INDEPENDENCE CONTRACT: authored from scenario intent and the documented,
 * observable indicator vocabulary. Never produced by executing the inference
 * service. Indicators are observable learning-behavior labels, never psychological
 * or clinical states. Optional fields left null/empty are not asserted.
 */
final readonly class ExpectedIndicators
{
    /**
     * @param  list<LearningStateValue>  $acceptableStates
     * @param  list<string>  $requiredBehavioral  Observable behavioral indicators that must all be present.
     * @param  list<string>  $forbiddenBehavioral  Behavioral indicators that must be absent.
     */
    public function __construct(
        public ?string $cognitiveIndicator = null,
        public bool $expectCognitiveNull = false,
        public ?string $psychomotorIndicator = null,
        public bool $expectPsychomotorNull = false,
        public array $requiredBehavioral = [],
        public array $forbiddenBehavioral = [],
        public bool $expectBehavioralEmpty = false,
        public ?LearningStateValue $expectedState = null,
        public array $acceptableStates = [],
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cognitive_indicator' => $this->cognitiveIndicator,
            'expect_cognitive_null' => $this->expectCognitiveNull,
            'psychomotor_indicator' => $this->psychomotorIndicator,
            'expect_psychomotor_null' => $this->expectPsychomotorNull,
            'required_behavioral' => $this->requiredBehavioral,
            'forbidden_behavioral' => $this->forbiddenBehavioral,
            'expect_behavioral_empty' => $this->expectBehavioralEmpty,
            'expected_state' => $this->expectedState?->value,
            'acceptable_states' => array_map(fn (LearningStateValue $s): string => $s->value, $this->acceptableStates),
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
