<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\InterventionType;

/**
 * Independently authored expectation for T04 adaptive intervention selection (M6-03).
 *
 * INDEPENDENCE CONTRACT: authored from scenario intent and documented M4-T04
 * behavior, never produced by executing AdaptiveInterventionService.
 */
final readonly class ExpectedIntervention
{
    /**
     * @param  list<InterventionType>  $acceptableTypes  Types allowed as a soft (REVIEW) match; empty means the primary type is the only match.
     */
    public function __construct(
        public bool $expectRemedial,
        public bool $expectStrong,
        public bool $expectSocratic,
        public ?InterventionType $interventionType = null,
        public ?string $selectionRule = null,
        public array $acceptableTypes = [],
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'expect_remedial' => $this->expectRemedial,
            'expect_strong' => $this->expectStrong,
            'expect_socratic' => $this->expectSocratic,
            'intervention_type' => $this->interventionType?->value,
            'selection_rule' => $this->selectionRule,
            'acceptable_types' => array_map(fn (InterventionType $t): string => $t->value, $this->acceptableTypes),
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
