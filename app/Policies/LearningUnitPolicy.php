<?php

namespace App\Policies;

use App\Models\LearningUnit;
use App\Models\Module;
use App\Models\User;

class LearningUnitPolicy
{
    public function viewAny(User $user, Module $module): bool
    {
        return $user->can('update', $module);
    }

    public function create(User $user, Module $module): bool
    {
        return $user->can('update', $module);
    }

    public function update(User $user, LearningUnit $learningUnit): bool
    {
        return $user->can('update', $learningUnit->module);
    }

    public function delete(User $user, LearningUnit $learningUnit): bool
    {
        return $this->update($user, $learningUnit) && $learningUnit->canBeDeleted();
    }

    public function reorder(User $user, Module $module): bool
    {
        return $user->can('update', $module);
    }

    public function publish(User $user, LearningUnit $learningUnit): bool
    {
        return $this->update($user, $learningUnit) && $learningUnit->canBePublished();
    }

    public function unpublish(User $user, LearningUnit $learningUnit): bool
    {
        return $this->update($user, $learningUnit);
    }
}
