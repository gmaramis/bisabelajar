<?php

namespace App\Enums;

enum ActivityType: string
{
    case Lesson = 'lesson';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case CodingExercise = 'coding_exercise';
    case Discussion = 'discussion';
    case Project = 'project';
    case Exam = 'exam';
}
