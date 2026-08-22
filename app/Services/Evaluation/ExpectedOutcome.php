<?php

namespace App\Services\Evaluation;

use App\Enums\InterventionType;
use App\Enums\LearningStateValue;
use App\Enums\NextLearningActionType;

/**
 * Independently authored expected outcome for an evaluation scenario (M6-01).
 *
 * INDEPENDENCE CONTRACT:
 * Every field on this object is authored by the scenario designer. It must never
 * be produced by calling the production rule under test (LearningStateInferenceService,
 * AdaptiveInterventionService, NextLearningActionService, or NexusClosedLoopService).
 * The evaluation runner and comparator never populate these values from production output.
 */
final readonly class ExpectedOutcome
{
    /**
     * @param  list<LearningStateValue>  $acceptableStates  States that count as a (soft) match; when empty the primary state is the only match.
     * @param  list<NextLearningActionType>  $acceptableNextActions  Actions that count as a (soft) match; when empty the primary action is the only match.
     */
    public function __construct(
        public LearningStateValue $state,
        public NextLearningActionType $nextAction,
        public bool $expectRemedialIntervention,
        public ?InterventionType $interventionType = null,
        public array $acceptableStates = [],
        public array $acceptableNextActions = [],
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
            'next_action' => $this->nextAction->value,
            'expect_remedial_intervention' => $this->expectRemedialIntervention,
            'intervention_type' => $this->interventionType?->value,
            'acceptable_states' => array_map(fn (LearningStateValue $s): string => $s->value, $this->acceptableStates),
            'acceptable_next_actions' => array_map(fn (NextLearningActionType $a): string => $a->value, $this->acceptableNextActions),
            'ambiguous' => $this->ambiguous,
            'review_when_low_confidence' => $this->reviewWhenLowConfidence,
            'rationale' => $this->rationale,
        ];
    }
}
