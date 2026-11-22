<?php

namespace App\Policies;

use App\Models\EvidenceStream;
use App\Models\User;

/**
 * A stream holds the HMAC secret a producer signs with. Only admins may list,
 * view or change one; everyone else sees the evidence the stream produced.
 */
class EvidenceStreamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, EvidenceStream $stream): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EvidenceStream $stream): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, EvidenceStream $stream): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
