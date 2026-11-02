<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Enums\TransferStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Support\FormQuote;
use App\Models\Reservation;
use App\Models\Transfer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpsStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = Transfer::onDate(today())->where('status', '!=', TransferStatus::Cancelled);
        $todayTotal = (clone $today)->count();
        $todayDone = (clone $today)->whereIn('status', [TransferStatus::Completed, TransferStatus::NoShow])->count();
        $live = Transfer::whereIn('status', [TransferStatus::EnRoute, TransferStatus::OnBoard])->count();

        $unassigned = Transfer::where('status', TransferStatus::Pending)->whereBetween('pickup_at', [now(), now()->addDay()])->count();
        $pending = Reservation::where('status', ReservationStatus::Pending)->count();

        $revenue = fn ($from, $to) => (float) Reservation::where('status', '!=', ReservationStatus::Cancelled)
            ->whereBetween('created_at', [$from, $to])->sum('total_price');
        $thisMonth = $revenue(now()->startOfMonth(), now());
        $lastMonth = $revenue(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow());
        $trend = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100) : null;

        $daily = collect(range(13, 0))->map(fn ($d) => Reservation::whereDate('created_at', today()->subDays($d))->count())->all();

        return [
            Stat::make('Bugünkü transferler', $todayTotal)
                ->description("{$todayDone} tamamlandı · {$live} şu an yolda")
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary')
                ->url(TransferResource::getUrl('index', ['tab' => 'today'])),
            Stat::make('Atama bekleyen (24 saat)', $unassigned)
                ->description($unassigned ? 'Sürücü ataması gerekli' : 'Tüm transferler atanmış')
                ->descriptionIcon($unassigned ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($unassigned ? 'danger' : 'success')
                ->url(TransferResource::getUrl('index', ['tab' => 'unassigned'])),
            Stat::make('Onay bekleyen rezervasyon', $pending)
                ->description('Web sitesinden gelen talepler')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending ? 'warning' : 'gray')
                ->url(ReservationResource::getUrl('index', ['tab' => 'pending'])),
            Stat::make('Bu ay ciro', FormQuote::money($thisMonth))
                ->description($trend === null ? 'Geçen ay verisi yok' : ($trend >= 0 ? "Geçen aya göre +%{$trend}" : 'Geçen aya göre −%'.abs($trend)))
                ->descriptionIcon($trend !== null && $trend < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up')
                ->chart($daily)
                ->color($trend !== null && $trend < 0 ? 'danger' : 'success'),
        ];
    }
}
