<?php

namespace App\Policies;

use App\Models\LearningProgress;
use App\Models\LearningUnit;
use App\Models\User;

class LearningProgressPolicy
{
    public function view(User $user, LearningProgress $progress): bool
    {
        if ($user->is($progress->user)) {
            return true;
        }

        return $user->isTutor() && $progress->enrollment->course->isOwnedBy($user);
    }

    public function update(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('learn', $learningUnit);
    }
}
