<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\NextLearningActionType;

/**
 * Independently authored expectation for a T05 next-learning-action decision (M6-03).
 *
 * INDEPENDENCE CONTRACT: authored literals, never produced by executing
 * NextLearningActionService.
 */
final readonly class ExpectedNextAction
{
    /**
     * @param  list<NextLearningActionType>  $acceptableActions
     */
    public function __construct(
        public NextLearningActionType $action,
        public array $acceptableActions = [],
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action->value,
            'acceptable_actions' => array_map(fn (NextLearningActionType $a): string => $a->value, $this->acceptableActions),
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
