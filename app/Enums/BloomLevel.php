<?php

namespace App\Enums;

/**
 * Revised Bloom Taxonomy levels.
 *
 * Used as task cognitive demand, not demonstrated learner capability.
 */
enum BloomLevel: string
{
    case Remember = 'remember';
    case Understand = 'understand';
    case Apply = 'apply';
    case Analyze = 'analyze';
    case Evaluate = 'evaluate';
    case Create = 'create';
}
