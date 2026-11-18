<?php

namespace App\Policies;

use App\Models\ControlValidationRule;
use App\Models\User;

/**
 * Validation rules decide what "compliant" means for a control. They are
 * configuration, so only admins may create or change them; a rule change is a
 * compliance decision, not day-to-day editing.
 */
class ControlValidationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ControlValidationRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ControlValidationRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ControlValidationRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
