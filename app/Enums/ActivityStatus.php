<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
