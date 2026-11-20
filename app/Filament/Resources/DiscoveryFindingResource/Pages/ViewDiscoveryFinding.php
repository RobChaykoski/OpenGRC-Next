<?php

namespace App\Filament\Resources\DiscoveryFindingResource\Pages;

use App\Filament\Resources\DiscoveryFindingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewDiscoveryFinding extends ViewRecord
{
    protected static string $resource = DiscoveryFindingResource::class;

    /**
     * Findings are synced from OpenDiscoveryEngine and reviewed here; the console
     * is read-only and never acknowledges or suppresses a finding in place.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
