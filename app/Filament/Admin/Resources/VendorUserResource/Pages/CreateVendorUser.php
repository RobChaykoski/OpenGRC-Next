<?php

namespace App\Filament\Admin\Resources\VendorUserResource\Pages;

use App\Filament\Admin\Resources\VendorUserResource;
use App\Services\Access\RegistrationGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorUser extends CreateRecord
{
    protected static string $resource = VendorUserResource::class;

    protected function beforeCreate(): void
    {
        app(RegistrationGuard::class)->assertAllowed(
            'data.email',
            (string) data_get($this->data, 'email'),
            request()->ip(),
        );
    }

    protected function afterCreate(): void
    {
        // TODO: Send invitation email with magic link
        VendorUserResource::resendInvitation($this->record);
    }
}
