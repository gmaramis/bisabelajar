<?php

namespace App\Enums;

/**
 * Lifecycle status for AI-assisted reassessment candidates (M5-04).
 *
 * Eligibility and validation are rule-based; generation is candidate-only.
 */
enum ReassessmentCandidateStatus: string
{
    case NotEligibleInsufficientEvidence = 'not_eligible_insufficient_evidence';
    case NotEligibleRecovered = 'not_eligible_recovered';
    case EligiblePendingGeneration = 'eligible_pending_generation';
    case Generated = 'generated';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case GenerationFailed = 'generation_failed';
    case ValidationFailed = 'validation_failed';
}
