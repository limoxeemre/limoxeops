<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ZoneType: string implements HasColor, HasIcon, HasLabel
{
    case Airport = 'airport';
    case City = 'city';
    case Resort = 'resort';
    case Port = 'port';

    public function getLabel(): string
    {
        return match ($this) {
            self::Airport => 'Havalimanı',
            self::City => 'Şehir / İlçe',
            self::Resort => 'Tatil Bölgesi',
            self::Port => 'Liman',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Airport => 'info',
            self::City => 'gray',
            self::Resort => 'success',
            self::Port => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Airport => 'heroicon-o-paper-airplane',
            self::City => 'heroicon-o-building-office-2',
            self::Resort => 'heroicon-o-sun',
            self::Port => 'heroicon-o-lifebuoy',
        };
    }
}
