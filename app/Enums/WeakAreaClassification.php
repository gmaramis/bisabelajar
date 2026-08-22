<?php

namespace App\Enums;

/**
 * Weak-area classification (M5-03).
 *
 * Evidence-derived research labels only — not psychological diagnosis
 * and not a permanent learner identity.
 */
enum WeakAreaClassification: string
{
    case WeakPersistent = 'weak_persistent';
    case WeakRepeatedFailure = 'weak_repeated_failure';
    case WeakUnresolved = 'weak_unresolved';
    case InsufficientEvidence = 'insufficient_evidence';
    case NoCurrentWeakness = 'no_current_weakness';
}
