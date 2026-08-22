<?php

namespace App\Enums;

/**
 * Observable intervention-response classification (M5-05).
 *
 * Labels observed learning evidence after support — not emotional response
 * and not causal effectiveness.
 */
enum InterventionResponseClassification: string
{
    case PositiveResponse = 'positive_response';
    case PartialResponse = 'partial_response';
    case NoObservedResponse = 'no_observed_response';
    case NegativeOrPersistentDifficulty = 'negative_or_persistent_difficulty';
    case InsufficientEvidence = 'insufficient_evidence';
}
