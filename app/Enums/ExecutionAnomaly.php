<?php

namespace App\Enums;

enum ExecutionAnomaly: string
{
    case None = 'none';
    case Detected = 'detected';
    case Unknown = 'unknown';
}
