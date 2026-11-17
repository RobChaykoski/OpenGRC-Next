<?php

namespace App\Policies;

use App\Models\Survey;
use App\Models\User;
use App\Models\VendorUser;

/**
 * Surveys are read by internal roles and by the vendor they belong to.
 *
 * The vendor guard has its own user model, so the policy accepts both. Internal
 * users keep the three-role behaviour; a vendor user only ever sees surveys for
 * their own vendor, independent of the query scope in the resource.
 */
class SurveyPolicy
{
    public function viewAny(User|VendorUser $user): bool
    {
        return true;
    }

    public function view(User|VendorUser $user, Survey $survey): bool
    {
        if ($user instanceof User) {
            return true;
        }

        return $survey->vendor_id === $user->vendor_id;
    }

    public function create(User|VendorUser $user): bool
    {
        return $user instanceof User ? $user->canWrite() : false;
    }

    public function update(User|VendorUser $user, Survey $survey): bool
    {
        if ($user instanceof User) {
            return $user->canWrite();
        }

        return $survey->vendor_id === $user->vendor_id;
    }

    public function delete(User|VendorUser $user, Survey $survey): bool
    {
        return $user instanceof User && $user->canDeleteRecords();
    }

    public function deleteAny(User|VendorUser $user): bool
    {
        return $user instanceof User && $user->canDeleteRecords();
    }
}
