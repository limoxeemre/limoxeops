<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Yeni Rezervasyon')->icon('heroicon-o-plus')];
    }

    public function getTabs(): array
    {
        $pending = fn (Builder $query) => $query->where('status', ReservationStatus::Pending);
        $upcoming = fn (Builder $query) => $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereHas('transfers', fn ($query) => $query->where('pickup_at', '>=', now()));

        return [
            'all' => Tab::make('Tümü'),
            'pending' => Tab::make('Onay Bekleyen')->modifyQueryUsing($pending)
                ->badge($pending(Reservation::query())->count() ?: null)->badgeColor('warning'),
            'upcoming' => Tab::make('Yaklaşan')->modifyQueryUsing($upcoming),
            'today' => Tab::make('Bugün Oluşturulan')->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today())),
            'cancelled' => Tab::make('İptal')->modifyQueryUsing(fn (Builder $query) => $query->where('status', ReservationStatus::Cancelled)),
        ];
    }
}
