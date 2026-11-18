<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

/**
 * Confidential and restricted templates are visible only to writers. The list
 * query applies the same rule so a title does not leak in the table.
 */
class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DocumentTemplate $template): bool
    {
        return $template->requiresWriter() ? $user->canWrite() : true;
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $user->canWrite();
    }

    public function delete(User $user, DocumentTemplate $template): bool
    {
        return $user->canDeleteRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canDeleteRecords();
    }

    public function approve(User $user, DocumentTemplate $template): bool
    {
        return $user->canWrite();
    }
}
