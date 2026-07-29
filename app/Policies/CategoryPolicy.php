<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Both admin and instructor can browse categories (read-only for
     * instructors, per the SRS's admin/instructor content-management split).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Instructor);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Only admins author content.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
