<?php

namespace App\Enums;

/**
 * Evidence sufficiency for a contextual bucket (M5-06).
 *
 * Descriptive only — not statistical significance.
 */
enum ContextEvidenceSufficiency: string
{
    case InsufficientEvidence = 'insufficient_evidence';
    case LimitedContextEvidence = 'limited_context_evidence';
    case ObservedContextPattern = 'observed_context_pattern';
}
