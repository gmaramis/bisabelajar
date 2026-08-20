<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\ActivitySubmission;
use App\Models\User;

class ActivitySubmissionPolicy
{
    public function view(User $user, ActivitySubmission $submission): bool
    {
        if ($user->is($submission->user)) {
            return true;
        }

        return $user->isTutor() && $submission->enrollment->course->isOwnedBy($user);
    }

    public function create(User $user, Activity $activity): bool
    {
        return $user->can('submit', $activity);
    }
}
