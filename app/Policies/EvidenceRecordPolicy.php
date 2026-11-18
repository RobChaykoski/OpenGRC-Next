<?php

namespace App\Policies;

use App\Models\EvidenceRecord;
use App\Models\User;

/**
 * Evidence is evidence: nobody edits or deletes a record, including an admin.
 * Corrections are new records. Read access follows the programme, so auditors
 * and viewers can inspect the chain.
 */
class EvidenceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EvidenceRecord $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, EvidenceRecord $record): bool
    {
        return false;
    }

    public function delete(User $user, EvidenceRecord $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, EvidenceRecord $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, EvidenceRecord $record): bool
    {
        return false;
    }
}
