<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of source OpenGRC can collect machine evidence from.
 *
 * The list is deliberately explicit outside the connector registry: it is a
 * filter and a label, not a schema. Adding a connector adds a case here.
 */
enum EvidenceSourceType: string implements HasLabel
{
    case Patchmon = 'patchmon';
    case Wazuh = 'wazuh';
    case OpenMetadata = 'openmetadata';
    case MicrosoftGraph = 'microsoft_graph';
    case Defender = 'defender';
    case Nessus = 'nessus';
    case CrowdStrike = 'crowdstrike';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Patchmon => 'PatchMon',
            self::Wazuh => 'Wazuh',
            self::OpenMetadata => 'OpenMetadata',
            self::MicrosoftGraph => 'Microsoft Graph',
            self::Defender => 'Microsoft Defender',
            self::Nessus => 'Tenable Nessus',
            self::CrowdStrike => 'CrowdStrike',
            self::Other => 'Other',
        };
    }
}
