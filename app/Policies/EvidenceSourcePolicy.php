<?php

namespace App\Policies;

use App\Models\EvidenceSource;
use App\Models\User;

/**
 * A source holds the credentials used to read a vendor system. Even a masked
 * credential is a target, so only admins may list or change sources. Auditors
 * see the evidence the source produced, not the source configuration.
 */
class EvidenceSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, EvidenceSource $source): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EvidenceSource $source): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EvidenceSource $source): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
