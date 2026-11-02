<?php

namespace App\Filament\Resources\Transfers;

use App\Enums\TransferDirection;
use App\Enums\TransferStatus;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Transfer;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TransfersTable
{
    public static function configure(Table $table, bool $standalone = true, bool $showReservation = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'reservation.vehicleType', 'reservation.extras.extra', 'driver', 'vehicle', 'pickupZone', 'dropoffZone',
            ]))
            ->defaultSort('pickup_at')
            ->striped()
            ->poll($standalone ? '30s' : null)
            ->groups($standalone ? [
                Group::make('pickup_at')->label('Tarih')->collapsible()
                    ->getKeyFromRecordUsing(fn (Transfer $r) => $r->pickup_at->toDateString())
                    ->getTitleFromRecordUsing(fn (Transfer $r) => $r->pickup_at->translatedFormat('d F Y, l')),
                Group::make('driver.name')->label('Sürücü')->collapsible(),
            ] : [])
            ->defaultGroup($standalone ? 'pickup_at' : null)
            ->columns([
                TextColumn::make('pickup_at')->label('Saat')
                    ->dateTime('H:i')->weight('bold')->size('lg')
                    ->description(fn (Transfer $r) => $r->pickup_at->translatedFormat('d M D').($r->flight_number ? ' · ✈ '.$r->flight_number : ''))
                    ->sortable(),
                TextColumn::make('status')->label('Durum')->badge(),
                TextColumn::make('reservation.passenger_name')->label('Yolcu / Rez.')
                    ->weight('semibold')
                    ->searchable(['passenger_name', 'passenger_phone', 'code'])
                    ->description(fn (Transfer $r) => $r->reservation->code.' · '.($r->direction === TransferDirection::Return ? '↩ Dönüş' : '→ Gidiş'))
                    ->tooltip(fn (Transfer $r) => $r->reservation->passenger_phone)
                    ->url(fn (Transfer $r) => ReservationResource::getUrl('view', ['record' => $r->reservation_id]))
                    ->visible($showReservation),
                TextColumn::make('flight_number')->label('Uçuş')->icon('heroicon-m-paper-airplane')->placeholder('—')
                    ->description(fn (Transfer $r) => $r->flight_time?->format('H:i'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn (Transfer $r) => $r->flight_number ? 'https://www.flightradar24.com/data/flights/'.strtolower($r->flight_number) : null, true),
                TextColumn::make('pickup_address')->label('Güzergah')
                    ->formatStateUsing(fn (Transfer $r) => Str::limit($r->pickup_address, 32).' → '.Str::limit($r->dropoff_address, 32))
                    ->description(fn (Transfer $r) => ($r->pickupZone?->name ?? 'Bölge dışı').' → '.($r->dropoffZone?->name ?? 'Bölge dışı').($r->distance_km ? ' · '.round($r->distance_km).' km' : ''))
                    ->tooltip(fn (Transfer $r) => $r->pickup_address."\n→ ".$r->dropoff_address)
                    ->wrap()->searchable(['pickup_address', 'dropoff_address']),
                TextColumn::make('pax')->label('Kişi / Araç')
                    ->state(fn (Transfer $r) => self::paxLabel($r))
                    ->description(fn (Transfer $r) => $r->reservation->vehicleType->name)
                    ->tooltip(fn (Transfer $r) => $r->reservation->extras->map(fn ($e) => $e->extra->name.' × '.$e->quantity)->implode(', ') ?: null),
                TextColumn::make('driver.name')->label('Sürücü')
                    ->placeholder('Atanmadı')
                    ->badge()
                    ->color(fn (Transfer $r) => $r->driver_id ? 'info' : 'warning')
                    ->icon(fn (Transfer $r) => $r->driver_id ? 'heroicon-m-user' : 'heroicon-m-exclamation-triangle')
                    ->description(fn (Transfer $r) => $r->vehicle?->plate),
                TextColumn::make('price')->label('Fiyat')->money(config('transfer.currency'))->toggleable(isToggledHiddenByDefault: true)->alignEnd(),
            ])
            ->filters($standalone ? [
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $d) => $query->where('pickup_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($query, $d) => $query->where('pickup_at', '<=', $d.' 23:59:59')))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'Başlangıç: '.Carbon::parse($data['from'])->format('d.m.Y') : null,
                        ($data['until'] ?? null) ? 'Bitiş: '.Carbon::parse($data['until'])->format('d.m.Y') : null,
                    ])),
                SelectFilter::make('status')->label('Durum')->options(TransferStatus::class)->multiple(),
                SelectFilter::make('driver_id')->label('Sürücü')->relationship('driver', 'name')->searchable()->preload(),
                SelectFilter::make('pickup_zone_id')->label('Alış bölgesi')->relationship('pickupZone', 'name')->preload(),
                SelectFilter::make('dropoff_zone_id')->label('Bırakış bölgesi')->relationship('dropoffZone', 'name')->preload(),
                TernaryFilter::make('assigned')->label('Sürücü')
                    ->trueLabel('Atanmış')->falseLabel('Atanmamış')
                    ->queries(true: fn ($query) => $query->whereNotNull('driver_id'), false: fn ($query) => $query->whereNull('driver_id')),
            ] : [])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions(TransferActions::row())
            ->toolbarActions($standalone ? [TransferActions::bulkAssign()] : [])
            ->recordClasses(fn (Transfer $r) => match (true) {
                $r->status === TransferStatus::Pending && $r->pickup_at->lt(now()->addHours(12)) => 'tr-row-urgent',
                ! $r->status->isActive() => 'tr-row-muted',
                default => null,
            })
            ->emptyStateHeading('Transfer yok')
            ->emptyStateDescription('Seçili filtrelere uyan transfer bulunamadı.')
            ->emptyStateIcon('heroicon-o-truck');
    }

    private static function paxLabel(Transfer $r): string
    {
        $res = $r->reservation;
        $parts = ["👤 {$res->adults}"];
        if ($res->children) {
            $parts[] = "🧒 {$res->children}";
        }
        if ($res->infants) {
            $parts[] = "👶 {$res->infants}";
        }
        $parts[] = "🧳 {$res->luggage}";

        return implode('  ', $parts);
    }
}
