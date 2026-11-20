<?php

namespace App\Services\Controls;

use App\Enums\ControlAttestationStatus;
use App\Models\ControlAttestation;
use App\Models\Implementation;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Request and record control-owner attestations.
 *
 * A request is idempotent while one is pending. The owner or an admin can
 * attest; an editor who is not the owner cannot answer on the owner's behalf.
 */
class ControlAttestationService
{
    public function request(
        Implementation $implementation,
        ?User $requestedBy = null,
        ?CarbonInterface $dueAt = null,
        ?string $requestNote = null,
    ): ControlAttestation {
        $existing = $implementation->attestations()
            ->pending()
            ->first();

        if ($existing instanceof ControlAttestation) {
            return $existing;
        }

        return $implementation->attestations()->create([
            'requested_by' => $requestedBy?->getKey() ?? auth()->id(),
            'requested_at' => now(),
            'due_at' => $dueAt ?? today()->addDays(14),
            'status' => ControlAttestationStatus::Pending,
            'request_note' => $requestNote,
        ]);
    }

    public function attest(ControlAttestation $attestation, User $user, string $statement): ControlAttestation
    {
        $this->assertCanRespond($attestation, $user);
        $this->assertPending($attestation);

        $attestation->update([
            'status' => ControlAttestationStatus::Attested,
            'attested_by' => $user->getKey(),
            'attested_at' => now(),
            'statement' => $statement,
        ]);

        return $attestation->refresh();
    }

    public function decline(ControlAttestation $attestation, User $user, string $reason): ControlAttestation
    {
        $this->assertCanRespond($attestation, $user);
        $this->assertPending($attestation);

        $attestation->update([
            'status' => ControlAttestationStatus::Declined,
            'attested_by' => $user->getKey(),
            'attested_at' => now(),
            'statement' => $reason,
        ]);

        return $attestation->refresh();
    }

    public function expire(): int
    {
        return ControlAttestation::query()
            ->overdue()
            ->update(['status' => ControlAttestationStatus::Expired->value]);
    }

    protected function assertCanRespond(ControlAttestation $attestation, User $user): void
    {
        $ownerId = $attestation->implementation?->implementation_owner_id;

        if (! $user->isAdmin() && (int) $ownerId !== (int) $user->getKey()) {
            throw new InvalidArgumentException('Only the implementation owner or an admin can respond to this attestation.');
        }
    }

    protected function assertPending(ControlAttestation $attestation): void
    {
        if (! $attestation->status->isOpen()) {
            throw new InvalidArgumentException('This attestation is no longer pending.');
        }
    }
}
