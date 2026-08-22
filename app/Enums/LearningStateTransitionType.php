<?php

namespace App\Enums;

/**
 * Observed Learning State transition patterns (M5-02).
 *
 * These label trajectory observations only — not causal improvement
 * or intervention effectiveness (M5-05+).
 */
enum LearningStateTransitionType: string
{
    case PositiveTransition = 'positive_transition';
    case Stabilization = 'stabilization';
    case PersistentSupportNeed = 'persistent_support_need';
    case DeteriorationSignal = 'deterioration_signal';
    case StableContinuation = 'stable_continuation';
    case ContinuedProgressing = 'continued_progressing';
    case InsufficientOrAmbiguous = 'insufficient_or_ambiguous';
}
