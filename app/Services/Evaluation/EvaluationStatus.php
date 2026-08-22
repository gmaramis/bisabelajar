<?php

namespace App\Services\Evaluation;

/**
 * Outcome of comparing an independently authored Expected outcome against the
 * Actual outcome produced by the NEXUS pipeline for one evaluation scenario.
 *
 * This is an evaluation-only artifact. It is not a learning state, a grade, or
 * a production decision, and it never feeds back into M3/M4/M5 behavior.
 */
enum EvaluationStatus: string
{
    case Pass = 'PASS';
    case Fail = 'FAIL';
    case Review = 'REVIEW';
}
