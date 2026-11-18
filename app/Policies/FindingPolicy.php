<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

/**
 * Findings are read by everyone who can see the programme, written by editors,
 * and deleted only by admins. Risk acceptance is a write, but it has its own
 * ability so it can be tightened without changing update rights.
 */
class FindingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Finding $finding): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, Finding $finding): bool
    {
        return $user->canWrite();
    }

    public function delete(User $user, Finding $finding): bool
    {
        return $user->canDeleteRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canDeleteRecords();
    }

    public function resolve(User $user, Finding $finding): bool
    {
        return $user->canWrite();
    }

    public function accept(User $user, Finding $finding): bool
    {
        return $user->canWrite();
    }

    public function restore(User $user, Finding $finding): bool
    {
        return $user->canDeleteRecords();
    }

    public function forceDelete(User $user, Finding $finding): bool
    {
        return $user->canDeleteRecords();
    }
}
