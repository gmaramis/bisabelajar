<?php

namespace App\Enums;

/**
 * Learning State V1 values.
 *
 * needs_support is a state label, not an adaptive intervention.
 */
enum LearningStateValue: string
{
    case Progressing = 'progressing';
    case Stable = 'stable';
    case NeedsSupport = 'needs_support';
    case InsufficientEvidence = 'insufficient_evidence';
}
