<?php

namespace App\Policies;

use App\Models\User;

/**
 * Default policy for every model that does not need ownership rules.
 *
 * Three roles: viewers read, editors write, admins administer. Models that need
 * per-record logic (audit items, data requests, attachments) keep their own
 * policy; everything else lands here.
 */
class AppPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $model = null): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->role->canWrite();
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $user->role->canDelete();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canDelete();
    }

    public function restore(User $user, mixed $model = null): bool
    {
        return $user->role->canDelete();
    }

    public function restoreAny(User $user): bool
    {
        return $user->role->canDelete();
    }

    public function forceDelete(User $user, mixed $model = null): bool
    {
        return $user->role->canDelete();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->role->canDelete();
    }

    public function replicate(User $user, mixed $model = null): bool
    {
        return $user->role->canWrite();
    }
}
