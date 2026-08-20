<?php

namespace App\Policies;

use App\Models\ActivityProgress;
use App\Models\User;

class ActivityProgressPolicy
{
    public function view(User $user, ActivityProgress $progress): bool
    {
        if ($user->is($progress->user)) {
            return true;
        }

        return $user->isTutor() && $progress->enrollment->course->isOwnedBy($user);
    }
}
