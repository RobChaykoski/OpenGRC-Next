<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The kind of assurance report or attestation.
 *
 * SOC 1 and SOC 3 are report types, not control frameworks; SOC 2 is the
 * Trust Services Criteria report. ISO and PCI records are certificates or
 * attestations of compliance, not the standard text.
 */
enum AttestationType: string implements HasColor, HasLabel
{
    case Soc1Type1 = 'soc1_type1';
    case Soc1Type2 = 'soc1_type2';
    case Soc2Type1 = 'soc2_type1';
    case Soc2Type2 = 'soc2_type2';
    case Soc3 = 'soc3';
    case Iso27001 = 'iso27001';
    case Iso27701 = 'iso27701';
    case PciAoc = 'pci_aoc';
    case PciRoc = 'pci_roc';
    case CmmcAffirmation = 'cmmc_affirmation';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Soc1Type1 => 'SOC 1 Type 1',
            self::Soc1Type2 => 'SOC 1 Type 2',
            self::Soc2Type1 => 'SOC 2 Type 1',
            self::Soc2Type2 => 'SOC 2 Type 2',
            self::Soc3 => 'SOC 3',
            self::Iso27001 => 'ISO/IEC 27001',
            self::Iso27701 => 'ISO/IEC 27701',
            self::PciAoc => 'PCI DSS AOC',
            self::PciRoc => 'PCI DSS ROC',
            self::CmmcAffirmation => 'CMMC affirmation',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Soc2Type2, self::Iso27001, self::Iso27701 => 'success',
            self::Soc1Type1, self::Soc1Type2, self::Soc2Type1 => 'info',
            self::Soc3, self::PciAoc, self::PciRoc => 'warning',
            self::CmmcAffirmation => 'gray',
            self::Other => 'gray',
        };
    }
}
