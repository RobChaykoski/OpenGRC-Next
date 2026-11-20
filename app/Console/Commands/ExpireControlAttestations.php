<?php

namespace App\Console\Commands;

use App\Services\Controls\ControlAttestationService;
use Illuminate\Console\Command;

class ExpireControlAttestations extends Command
{
    protected $signature = 'opengrc:controls:attestations:expire';

    protected $description = 'Mark pending control attestations past their due date as expired';

    public function handle(ControlAttestationService $service): int
    {
        $count = $service->expire();

        $this->info($count === 1
            ? 'Expired 1 control attestation.'
            : "Expired {$count} control attestations.");

        return self::SUCCESS;
    }
}
