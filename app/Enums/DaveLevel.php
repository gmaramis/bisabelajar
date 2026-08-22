<?php

namespace App\Enums;

/**
 * Dave's Psychomotor Taxonomy levels.
 *
 * Used as task psychomotor/skill demand, not demonstrated learner skill.
 */
enum DaveLevel: string
{
    case Imitation = 'imitation';
    case Manipulation = 'manipulation';
    case Precision = 'precision';
    case Articulation = 'articulation';
    case Naturalization = 'naturalization';
}
