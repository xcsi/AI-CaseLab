<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\User;

class CasePolicy
{
    /**
     * Both admin and instructor can browse the case list (read-only for
     * instructors, per the SRS's admin/instructor content-management split).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Instructor);
    }

    /**
     * Only admins author content.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, CaseModel $case): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, CaseModel $case): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
