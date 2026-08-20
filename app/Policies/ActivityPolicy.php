<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\LearningUnit;
use App\Models\User;

class ActivityPolicy
{
    public function view(User $user, Activity $activity): bool
    {
        if ($user->can('update', $activity->learningUnit)) {
            return true;
        }

        $unit = $activity->learningUnit;

        return $activity->isPublished() && $user->can('learn', $unit);
    }

    public function create(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('update', $learningUnit);
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('update', $activity->learningUnit);
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function reorder(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('update', $learningUnit);
    }

    public function publish(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity) && $activity->canBePublished();
    }

    public function unpublish(User $user, Activity $activity): bool
    {
        return $this->update($user, $activity);
    }
}
