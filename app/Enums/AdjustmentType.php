<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdjustmentType: string implements HasLabel
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percent => 'Yüzde (%)',
            self::Fixed => 'Sabit Tutar',
        };
    }
}
