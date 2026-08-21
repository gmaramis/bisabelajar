<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
