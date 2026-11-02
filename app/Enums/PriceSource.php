<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PriceSource: string implements HasColor, HasLabel
{
    case FixedRoute = 'fixed_route';
    case Distance = 'distance';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::FixedRoute => 'Sabit Rota',
            self::Distance => 'Km Bazlı',
            self::Manual => 'Manuel',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FixedRoute => 'success',
            self::Distance => 'warning',
            self::Manual => 'gray',
        };
    }
}
