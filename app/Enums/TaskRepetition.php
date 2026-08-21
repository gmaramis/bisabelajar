<?php

namespace App\Enums;

enum TaskRepetition: string
{
    case New = 'new';
    case Repeated = 'repeated';
    case Unknown = 'unknown';
}
