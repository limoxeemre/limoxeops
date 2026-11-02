<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tek bir transfer bacağının (leg) operasyonel durumu.
 * Akış: pending -> assigned -> en_route -> on_board -> completed
 *                                     \-> no_show
 */
enum TransferStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case EnRoute = 'en_route';
    case OnBoard = 'on_board';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Atama Bekliyor',
            self::Assigned => 'Sürücü Atandı',
            self::EnRoute => 'Yolda',
            self::OnBoard => 'Yolcu Alındı',
            self::Completed => 'Tamamlandı',
            self::NoShow => 'Yolcu Gelmedi',
            self::Cancelled => 'İptal',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Assigned => 'info',
            self::EnRoute => 'primary',
            self::OnBoard => 'primary',
            self::Completed => 'success',
            self::NoShow => 'danger',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-exclamation-triangle',
            self::Assigned => 'heroicon-o-user-circle',
            self::EnRoute => 'heroicon-o-truck',
            self::OnBoard => 'heroicon-o-users',
            self::Completed => 'heroicon-o-check-circle',
            self::NoShow => 'heroicon-o-user-minus',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /** Bu durumdan geçilebilecek durumlar. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Pending, self::EnRoute, self::Cancelled],
            self::EnRoute => [self::OnBoard, self::NoShow, self::Assigned],
            self::OnBoard => [self::Completed],
            self::Completed, self::NoShow, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Completed, self::NoShow, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, array_filter(self::cases(), fn (self $s) => $s->isActive()));
    }
}
