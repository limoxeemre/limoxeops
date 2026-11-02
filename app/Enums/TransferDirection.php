<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransferDirection: string implements HasColor, HasLabel
{
    case Outbound = 'outbound';
    case Return = 'return';

    public function getLabel(): string
    {
        return match ($this) {
            self::Outbound => 'Gidiş',
            self::Return => 'Dönüş',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Outbound => 'primary',
            self::Return => 'gray',
        };
    }
}
