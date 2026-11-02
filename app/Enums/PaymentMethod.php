<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case CashToDriver = 'cash';
    case CreditCard = 'card';
    case Online = 'online';
    case Invoice = 'invoice';

    public function getLabel(): string
    {
        return match ($this) {
            self::CashToDriver => 'Sürücüye Nakit',
            self::CreditCard => 'Araçta Kredi Kartı',
            self::Online => 'Online Ödeme',
            self::Invoice => 'Cari / Fatura (Acente)',
        };
    }
}
