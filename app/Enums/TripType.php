<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TripType: string implements HasLabel
{
    case OneWay = 'one_way';
    case RoundTrip = 'round_trip';

    public function getLabel(): string
    {
        return match ($this) {
            self::OneWay => 'Tek Yön',
            self::RoundTrip => 'Gidiş - Dönüş',
        };
    }
}
