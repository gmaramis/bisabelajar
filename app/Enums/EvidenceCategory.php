<?php

namespace App\Enums;

enum EvidenceCategory: string
{
    case Performance = 'performance';
    case Behavioral = 'behavioral';
    case Interaction = 'interaction';
    case SystemContext = 'system_context';
}
