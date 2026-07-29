<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Evaluation;
use App\Models\User;

class EvaluationPolicy
{
    /**
     * Both admin and instructor can browse and act on the review queue —
     * unlike case authoring (CasePolicy, admin-only), reviewing
     * submissions is an instructor-facing action per the SRS's Instructor
     * persona ("may review individual submissions").
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Instructor);
    }

    public function review(User $user, Evaluation $evaluation): bool
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Instructor);
    }
}
