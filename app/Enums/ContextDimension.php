<?php

namespace App\Enums;

/**
 * Available contextual analysis dimensions (M5-06).
 *
 * Only dimensions backed by existing schema. Campus/institution/cohort
 * are intentionally absent.
 */
enum ContextDimension: string
{
    case Course = 'course';
    case Module = 'module';
    case LearningUnit = 'learning_unit';
    case Activity = 'activity';
    case ProgrammingLanguage = 'programming_language';
    case BloomTaskDemand = 'bloom_task_demand';
    case DaveTaskDemand = 'dave_task_demand';
}
