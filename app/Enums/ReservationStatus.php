<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ReservationStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Onay Bekliyor',
            self::Confirmed => 'Onaylandı',
            self::Completed => 'Tamamlandı',
            self::Cancelled => 'İptal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-check-badge',
            self::Completed => 'heroicon-o-flag',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
