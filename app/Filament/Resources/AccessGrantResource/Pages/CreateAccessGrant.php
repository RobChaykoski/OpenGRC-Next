<?php

namespace App\Filament\Resources\AccessGrantResource\Pages;

use App\Filament\Resources\AccessGrantResource;
use App\Models\ProtectedResource;
use App\Models\User;
use App\Services\Access\AccessGrantService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * A grant is created through the service, not by the form, so the justification,
 * the TTL cap and the review obligation are applied in one place.
 */
class CreateAccessGrant extends CreateRecord
{
    protected static string $resource = AccessGrantResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated user is required to request access.');
        }

        $resource = ProtectedResource::query()->findOrFail((int) $data['protected_resource_id']);

        return app(AccessGrantService::class)->request($resource, $data, $user);
    }
}
