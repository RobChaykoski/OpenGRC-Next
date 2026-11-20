<?php

namespace App\Filament\Resources\DiscoveryScanResource\Pages;

use App\Filament\Resources\DiscoveryScanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewDiscoveryScan extends ViewRecord
{
    protected static string $resource = DiscoveryScanResource::class;

    /**
     * Scans are synced from OpenDiscoveryEngine; this console never writes back.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
