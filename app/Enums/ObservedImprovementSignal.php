<?php

namespace App\Enums;

/**
 * Observed improvement signal after intervention/support (M5-05).
 *
 * Explicitly non-causal: "observed improvement after intervention"
 * is not "intervention caused improvement".
 */
enum ObservedImprovementSignal: string
{
    case ObservedImprovement = 'observed_improvement';
    case StabilizationSignal = 'stabilization_signal';
    case NoObservedImprovement = 'no_observed_improvement';
    case DeteriorationSignal = 'deterioration_signal';
    case Inconclusive = 'inconclusive';
}
