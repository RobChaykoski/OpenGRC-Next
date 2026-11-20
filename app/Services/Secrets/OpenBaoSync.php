<?php

namespace App\Services\Secrets;

use App\Models\NonHumanIdentity;
use RuntimeException;
use Throwable;

/**
 * Refresh rotation dates from OpenBao metadata.
 *
 * The sync updates last_rotated_at, next_rotation_at and secret_synced_at only.
 * It does not change the status, read a secret value, or pull a dynamic lease.
 */
class OpenBaoSync
{
    public function __construct(protected OpenBaoClient $client) {}

    /**
     * @return array{checked: int, updated: int, skipped: int, failed: int, errors: array<int, string>}
     */
    public function sync(bool $dryRun = false): array
    {
        if (! $this->client->configured()) {
            throw new RuntimeException(
                'OpenBao is not configured. Set OPENBAO_URL and OPENBAO_TOKEN.'
            );
        }

        $identities = NonHumanIdentity::query()
            ->whereNotNull('secret_reference')
            ->where('secret_reference', 'like', 'openbao://%')
            ->get();

        $counts = [
            'checked' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($identities as $identity) {
            $counts['checked']++;

            $reference = is_string($identity->secret_reference) ? $identity->secret_reference : '';
            $parsed = $this->client->parseReference($reference);

            if ($parsed === null) {
                $counts['skipped']++;

                continue;
            }

            try {
                $metadata = $this->client->kvMetadata($parsed['mount'], $parsed['path']);
                $rotatedAt = $this->client->rotationDate($metadata);

                if ($rotatedAt === null) {
                    $counts['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $counts['updated']++;

                    continue;
                }

                $identity->forceFill([
                    'last_rotated_at' => $rotatedAt->toDateString(),
                    'next_rotation_at' => is_int($identity->rotation_days) && $identity->rotation_days > 0
                        ? $rotatedAt->addDays($identity->rotation_days)->toDateString()
                        : null,
                    'secret_synced_at' => now(),
                ])->save();

                $counts['updated']++;
            } catch (Throwable $exception) {
                $counts['failed']++;

                if (count($counts['errors']) < 5) {
                    $counts['errors'][] = "{$identity->name}: {$exception->getMessage()}";
                }
            }
        }

        return $counts;
    }
}
