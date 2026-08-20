<?php

namespace App\Enums;

enum CompletionRule: string
{
    case Submission = 'submission';
    case Manual = 'manual';
}
