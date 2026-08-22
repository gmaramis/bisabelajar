<?php

namespace App\Services\Evaluation\Intervention;

use App\Enums\ReassessmentCandidateStatus;

/**
 * Independently authored expectation for M5-04 AI-assisted reassessment (M6-03).
 *
 * INDEPENDENCE CONTRACT: authored literals, never produced by executing
 * AiAssistedReassessmentService.
 */
final readonly class ExpectedReassessment
{
    /**
     * @param  list<ReassessmentCandidateStatus>  $acceptableStatuses  Statuses that count as a match (structural validity may allow more than one).
     */
    public function __construct(
        public bool $expectEligible,
        public array $acceptableStatuses,
        public bool $expectConceptAlignment = false,
        public bool $expectCandidateContent = false,
        public bool $ambiguous = false,
        public string $rationale = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'expect_eligible' => $this->expectEligible,
            'acceptable_statuses' => array_map(fn (ReassessmentCandidateStatus $s): string => $s->value, $this->acceptableStatuses),
            'expect_concept_alignment' => $this->expectConceptAlignment,
            'expect_candidate_content' => $this->expectCandidateContent,
            'ambiguous' => $this->ambiguous,
            'rationale' => $this->rationale,
        ];
    }
}
