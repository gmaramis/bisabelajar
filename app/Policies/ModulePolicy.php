<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function viewAny(User $user, Course $course): bool
    {
        return $user->can('update', $course);
    }

    public function create(User $user, Course $course): bool
    {
        return $user->can('update', $course);
    }

    public function update(User $user, Module $module): bool
    {
        return $user->can('update', $module->course);
    }

    public function delete(User $user, Module $module): bool
    {
        return $this->update($user, $module) && $module->canBeDeleted();
    }

    public function reorder(User $user, Course $course): bool
    {
        return $user->can('update', $course);
    }

    public function publish(User $user, Module $module): bool
    {
        return $this->update($user, $module) && $module->canBePublished();
    }

    public function unpublish(User $user, Module $module): bool
    {
        return $this->update($user, $module);
    }
}
