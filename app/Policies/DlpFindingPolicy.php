<?php

namespace App\Policies;

use App\Models\DlpFinding;
use App\Models\User;

/**
 * DLP findings can reveal who attempted to send sensitive content where. Only
 * admins review them; the only write is the review disposition and notes.
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
        // Admins may record a review disposition. The finding content and the
        // detection metadata stay immutable.
        return $user->isAdmin();
    }

    public function delete(User $user, DlpFinding $finding): bool
    {
        return false;
    }
}
