<?php

namespace App\Filament\Resources\Transfers;

use App\Enums\TransferStatus;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Models\Transfer;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Operasyon panosu: günlük transfer listesi, sürücü atama ve durum takibi.
 */
class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Operasyon Panosu';

    protected static ?string $modelLabel = 'Transfer';

    protected static ?string $pluralModelLabel = 'Transferler';

    public static function canCreate(): bool
    {
        return false; // transferler rezervasyon üzerinden oluşur
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Transfer::where('status', TransferStatus::Pending)
            ->whereBetween('pickup_at', [now(), now()->addDay()])->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return '24 saat içinde ataması yapılmamış transfer';
    }

    public static function table(Table $table): Table
    {
        return TransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransfers::route('/'),
        ];
    }
}
