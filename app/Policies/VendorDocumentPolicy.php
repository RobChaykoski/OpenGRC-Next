<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorUser;

/**
 * Vendor documents are reviewed internally and uploaded by the owning vendor.
 */
class VendorDocumentPolicy
{
    public function viewAny(User|VendorUser $user): bool
    {
        return true;
    }

    public function view(User|VendorUser $user, VendorDocument $document): bool
    {
        if ($user instanceof User) {
            return true;
        }

        return $document->vendor_id === $user->vendor_id;
    }

    public function create(User|VendorUser $user): bool
    {
        return $user instanceof User ? $user->canWrite() : true;
    }

    public function update(User|VendorUser $user, VendorDocument $document): bool
    {
        if ($user instanceof User) {
            return $user->canWrite();
        }

        return $document->vendor_id === $user->vendor_id;
    }

    public function delete(User|VendorUser $user, VendorDocument $document): bool
    {
        return $user instanceof User && $user->canDeleteRecords();
    }

    public function deleteAny(User|VendorUser $user): bool
    {
        return $user instanceof User && $user->canDeleteRecords();
    }
}
