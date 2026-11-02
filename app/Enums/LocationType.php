<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LocationType: string implements HasIcon, HasLabel
{
    case Airport = 'airport';
    case Hotel = 'hotel';
    case Port = 'port';
    case Landmark = 'landmark';
    case Address = 'address';

    public function getLabel(): string
    {
        return match ($this) {
            self::Airport => 'Havalimanı Terminali',
            self::Hotel => 'Otel',
            self::Port => 'Liman',
            self::Landmark => 'Önemli Nokta',
            self::Address => 'Adres',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Airport => 'heroicon-o-paper-airplane',
            self::Hotel => 'heroicon-o-building-office',
            self::Port => 'heroicon-o-lifebuoy',
            self::Landmark => 'heroicon-o-star',
            self::Address => 'heroicon-o-map-pin',
        };
    }
}
