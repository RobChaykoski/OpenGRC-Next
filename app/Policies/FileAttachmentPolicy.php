<?php

namespace App\Policies;

use App\Models\Audit;
use App\Models\AuditItem;
use App\Models\FileAttachment;
use App\Models\User;
use Livewire;

class FileAttachmentPolicy
{
    protected string $model = FileAttachment::class;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FileAttachment $attachment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canWrite();
    }

    public function update(User $user, FileAttachment $attachment): bool
    {
        return $user->canWrite() && ($this->isOwner() || $this->isMember());
    }

    public function delete(User $user, FileAttachment $attachment): bool
    {
        return $user->isAdmin() && ($this->isOwner() || $this->isMember());
    }

    private function isOwner(): bool
    {
        $type = explode('/', Livewire::originalPath())[1];
        $audit_id = null;

        if ($type === 'audits') {
            $audit_id = explode('/', Livewire::originalPath())[2] ?? null;
        } elseif ($type == 'audit-items') {
            $audit_item_id = explode('/', Livewire::originalPath())[2] ?? null;
            $audit_id = AuditItem::find($audit_item_id)->audit_id;
        }

        if (! $audit_id) {
            return false;
        }

        $audit = Audit::find($audit_id);

        return $audit && $audit->manager_id === auth()->id();
    }

    private function isMember(): bool
    {
        $type = explode('/', Livewire::originalPath())[1];
        $audit_id = null;

        if ($type === 'audits') {
            $audit_id = explode('/', Livewire::originalPath())[2] ?? null;
        } elseif ($type == 'audit-items') {
            $audit_item_id = explode('/', Livewire::originalPath())[2] ?? null;
            $audit_id = AuditItem::find($audit_item_id)->audit_id;
        }

        if (! $audit_id) {
            return false;
        }

        $audit = Audit::find($audit_id);

        return $audit && $audit->members->contains(auth()->id());
    }
}
