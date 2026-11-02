<?php

namespace App\Filament\Resources\Reservations\RelationManagers;

use App\Filament\Resources\Transfers\TransferActions;
use App\Filament\Resources\Transfers\TransfersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransfersRelationManager extends RelationManager
{
    protected static string $relationship = 'transfers';

    protected static ?string $title = 'Transfer Bacakları';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-truck';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return TransfersTable::configure($table, standalone: false, showReservation: false)
            ->heading('Transfer Bacakları')
            ->description('Sürücü ataması, saat değişikliği ve durum takibi bacak bazında yapılır.')
            ->pushColumns([
                TextColumn::make('direction')->label('Yön')->badge(),
            ])
            ->recordActions([...TransferActions::row(), TransferActions::cancel()])
            ->paginated(false);
    }
}
