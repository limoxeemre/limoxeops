<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingSource: string implements HasColor, HasLabel
{
    case Website = 'website';
    case Agency = 'agency';
    case Admin = 'admin';
    case Phone = 'phone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => 'Web / API',
            self::Agency => 'Acente',
            self::Admin => 'Panel',
            self::Phone => 'Telefon',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Website => 'info',
            self::Agency => 'warning',
            self::Admin => 'gray',
            self::Phone => 'success',
        };
    }
}
