<?php

namespace App\Console\Commands;

use App\Enums\NonHumanIdentityStatus;
use App\Models\NonHumanIdentity;
use Illuminate\Console\Command;

/**
 * Surface non-human identity rotation and expiry.
 *
 * The command changes status, not credentials: an expired token is marked
 * Expired, a token past its rotation interval is RotationDue. Revocation and
 * rotation stay human actions in the secret store and the owning system.
 */
class ReviewNonHumanIdentities extends Command
{
    protected $signature = 'opengrc:identities:review
                            {--dry-run : Report what would change without updating status}';

    protected $description = 'Mark active non-human identities as expired or rotation due';

    public function handle(): int
    {
        $expired = NonHumanIdentity::query()
            ->where('status', NonHumanIdentityStatus::Active)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', today());

        $rotationDue = NonHumanIdentity::query()
            ->where('status', NonHumanIdentityStatus::Active)
            ->whereNotNull('next_rotation_at')
            ->whereDate('next_rotation_at', '<', today());

        $expiredCount = (clone $expired)->count();
        $rotationCount = (clone $rotationDue)->count();

        $dueSoon = NonHumanIdentity::query()
            ->whereIn('status', [
                NonHumanIdentityStatus::Active->value,
                NonHumanIdentityStatus::RotationDue->value,
                NonHumanIdentityStatus::PendingReview->value,
            ])
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('next_rotation_at')
                        ->whereDate('next_rotation_at', '>=', today())
                        ->whereDate('next_rotation_at', '<=', today()->addDays(14));
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('expires_at')
                        ->whereDate('expires_at', '>=', today())
                        ->whereDate('expires_at', '<=', today()->addDays(14));
                });
            })
            ->count();

        if ($expiredCount === 0 && $rotationCount === 0 && $dueSoon === 0) {
            $this->info('No non-human identities are expired, due for rotation or due soon.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(
                "{$expiredCount} would be marked expired; {$rotationCount} rotation due; {$dueSoon} due within 14 days."
            );

            return self::SUCCESS;
        }

        $expiredUpdated = $expired->update([
            'status' => NonHumanIdentityStatus::Expired,
            'updated_at' => now(),
        ]);

        $rotationUpdated = $rotationDue->update([
            'status' => NonHumanIdentityStatus::RotationDue,
            'updated_at' => now(),
        ]);

        $this->info(
            "Marked {$expiredUpdated} expired and {$rotationUpdated} rotation due; {$dueSoon} due within 14 days."
        );

        return self::SUCCESS;
    }
}
