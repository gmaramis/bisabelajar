<?php

namespace App\Enums;

/**
 * Simple Socratic response forms for M4-T04 V1.
 */
enum SocraticResponseType: string
{
    case ClarifyingQuestion = 'clarifying_question';
    case ConceptCheck = 'concept_check';
    case GuidedQuestion = 'guided_question';
    case ReflectionQuestion = 'reflection_question';
    case NextStepHint = 'next_step_hint';
}
