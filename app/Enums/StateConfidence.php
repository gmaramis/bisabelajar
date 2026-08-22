<?php

namespace App\Enums;

/**
 * Confidence in the inferred Learning State.
 *
 * Distinct from M4-T02 evidence confidence.
 */
enum StateConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
