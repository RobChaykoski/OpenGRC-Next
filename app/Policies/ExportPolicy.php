<?php

namespace App\Policies;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;

/**
 * Exports are owned by the user who requested them.
 *
 * Filament falls back to an ownership check when no policy exists, but the
 * default AppPolicy would otherwise answer every view with true and let any
 * authenticated user download another user's export by id.
 */
class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        $owner = $export->user;

        return $user->isAdmin() || ($owner !== null && $owner->is($user));
    }
}
