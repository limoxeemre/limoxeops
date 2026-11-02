<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Enums\TransferStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransfers extends ListRecords
{
    protected static string $resource = TransferResource::class;

    protected static ?string $title = 'Operasyon Panosu';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newReservation')->label('Yeni Rezervasyon')->icon('heroicon-o-plus')
                ->url(ReservationResource::getUrl('create')),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (callable $scope) => $scope(Transfer::query())->count();

        $today = fn (Builder $query) => $query->onDate(today())->where('status', '!=', TransferStatus::Cancelled);
        $tomorrow = fn (Builder $query) => $query->onDate(today()->addDay())->where('status', '!=', TransferStatus::Cancelled);
        $unassigned = fn (Builder $query) => $query->where('status', TransferStatus::Pending)->where('pickup_at', '>=', now()->subHours(2));
        $live = fn (Builder $query) => $query->whereIn('status', [TransferStatus::EnRoute, TransferStatus::OnBoard]);
        $upcoming = fn (Builder $query) => $query->active()->where('pickup_at', '>=', today());

        return [
            'today' => Tab::make('Bugün')->icon('heroicon-m-sun')->modifyQueryUsing($today)->badge($count($today)),
            'tomorrow' => Tab::make('Yarın')->modifyQueryUsing($tomorrow)->badge($count($tomorrow)),
            'unassigned' => Tab::make('Atanmamış')->icon('heroicon-m-exclamation-triangle')->modifyQueryUsing($unassigned)
                ->badge($count($unassigned))->badgeColor('danger'),
            'live' => Tab::make('Yolda')->icon('heroicon-m-signal')->modifyQueryUsing($live)->badge($count($live))->badgeColor('primary'),
            'upcoming' => Tab::make('Tüm Yaklaşanlar')->modifyQueryUsing($upcoming),
            'all' => Tab::make('Tümü'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
