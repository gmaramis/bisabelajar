<?php

namespace App\Enums;

/**
 * Next learning action decided by M4-T05 V1.
 *
 * reassessment means the learner should be retested on the same capability;
 * it does not generate reassessment questions.
 */
enum NextLearningActionType: string
{
    case Continue = 'continue';
    case ReviewConcept = 'review_concept';
    case PracticeAgain = 'practice_again';
    case GuidedRetry = 'guided_retry';
    case Reassessment = 'reassessment';
    case CollectMoreEvidence = 'collect_more_evidence';
}
