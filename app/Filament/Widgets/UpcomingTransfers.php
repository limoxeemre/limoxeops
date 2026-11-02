<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Transfers\TransfersTable;
use App\Models\Transfer;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingTransfers extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Sıradaki 12 saat';

    public function table(Table $table): Table
    {
        return TransfersTable::configure($table, standalone: false)
            ->query(Transfer::query()->active()->whereBetween('pickup_at', [now()->subHour(), now()->addHours(12)]))
            ->heading('Sıradaki 12 saat')
            ->description('Aktif transferler · kırmızı işaretli satırlar sürücü bekliyor')
            ->poll('30s')
            ->paginated([10, 25])
            ->emptyStateHeading('Önümüzdeki 12 saatte transfer yok');
    }
}
