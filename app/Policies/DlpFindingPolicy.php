<?php

namespace App\Policies;

use App\Models\DlpFinding;
use App\Models\User;

/**
 * DLP findings can reveal who attempted to send sensitive content where. Only
 * admins review them; there is no write path.
 */
class DlpFindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, DlpFinding $finding): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DlpFinding $finding): bool
    {
        return false;
    }

    public function delete(User $user, DlpFinding $finding): bool
    {
        return false;
    }
}
