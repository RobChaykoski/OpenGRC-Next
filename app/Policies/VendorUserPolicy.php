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
        return $user->can('Manage Vendor Management');
    }

    public function view(User $user, VendorUser $vendorUser): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function create(User $user): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function update(User $user, VendorUser $vendorUser): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function delete(User $user, VendorUser $vendorUser): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function restore(User $user, VendorUser $vendorUser): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function forceDelete(User $user, VendorUser $vendorUser): bool
    {
        return $user->can('Manage Vendor Management');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('Manage Vendor Management');
    }
}
