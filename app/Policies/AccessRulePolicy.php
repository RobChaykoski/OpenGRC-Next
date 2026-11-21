<?php

namespace App\Policies;

use App\Models\AccessRule;
use App\Models\User;

/**
 * Access rules are a security control. Only administrators may read or change
 * them; editors and viewers do not get a read-only view in the app panel.
 */
class AccessRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AccessRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AccessRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AccessRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, AccessRule $rule): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, AccessRule $rule): bool
    {
        return $user->isAdmin();
    }
}
