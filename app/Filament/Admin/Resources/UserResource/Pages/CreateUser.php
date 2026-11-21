<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Services\Access\RegistrationGuard;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function beforeCreate(): void
    {
        app(RegistrationGuard::class)->assertAllowed(
            'data.email',
            (string) data_get($this->data, 'email'),
            request()->ip(),
        );
    }
}
