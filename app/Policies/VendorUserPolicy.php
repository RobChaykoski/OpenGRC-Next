<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorUser;

/**
 * Vendor portal accounts are managed from the admin panel.
 *
 * Without an explicit policy, Filament falls back to "allowed", which made
 * vendor user records readable and creatable by any authenticated user. This was
 * caught by tests/Feature/AuthorizationMatrixTest.php.
 */
class VendorUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, VendorUser $vendorUser): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, VendorUser $vendorUser): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, VendorUser $vendorUser): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, VendorUser $vendorUser): bool
    {
        return $user->isAdmin();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, VendorUser $vendorUser): bool
    {
        return $user->isAdmin();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
