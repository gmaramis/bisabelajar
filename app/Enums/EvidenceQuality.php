<?php

namespace App\Enums;

enum EvidenceQuality: string
{
    case Valid = 'valid';
    case Uncertain = 'uncertain';
    case ContextDependent = 'context_dependent';
}
