<?php

namespace App\Enums;

enum InterventionType: string
{
    case Hint = 'hint';
    case SocraticQuestion = 'socratic_question';
    case ConceptExplanation = 'concept_explanation';
    case WorkedExample = 'worked_example';
    case CorrectiveFeedback = 'corrective_feedback';
    case GuidedRetry = 'guided_retry';
    case Reinforcement = 'reinforcement';
}
