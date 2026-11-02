<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Enums\BookingSource;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TripType;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['outbound.pickupZone', 'outbound.dropoffZone', 'return', 'vehicleType', 'agency']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Kod')->weight('bold')->fontFamily('mono')->searchable()->copyable()
                    ->description(fn (Reservation $r) => $r->created_at->diffForHumans()),
                TextColumn::make('passenger_name')->label('Yolcu')->searchable(['passenger_name', 'passenger_phone', 'passenger_email'])
                    ->description(fn (Reservation $r) => $r->passenger_phone),
                TextColumn::make('outbound.pickup_at')->label('Transfer')->dateTime('d.m.Y H:i')
                    ->description(fn (Reservation $r) => $r->trip_type === TripType::RoundTrip && $r->return
                        ? '↩ '.$r->return->pickup_at->format('d.m.Y H:i') : 'Tek yön'),
                TextColumn::make('route')->label('Güzergah')
                    ->state(fn (Reservation $r) => ($r->outbound?->pickupZone?->name ?? 'Özel adres').' → '.($r->outbound?->dropoffZone?->name ?? 'Özel adres'))
                    ->description(fn (Reservation $r) => $r->vehicleType->name.' · '.$r->total_passengers.' kişi'),
                TextColumn::make('total_price')->label('Tutar')->money(fn (Reservation $r) => $r->currency)->sortable()->alignEnd()
                    ->description(fn (Reservation $r) => $r->payment_status->getLabel()),
                TextColumn::make('status')->label('Durum')->badge(),
                TextColumn::make('source')->label('Kaynak')->badge()
                    ->description(fn (Reservation $r) => $r->agency?->name),
                TextColumn::make('created_at')->label('Oluşturma')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')->options(ReservationStatus::class)->multiple(),
                SelectFilter::make('source')->label('Kaynak')->options(BookingSource::class),
                SelectFilter::make('agency_id')->label('Acente')->relationship('agency', 'name')->searchable()->preload(),
                SelectFilter::make('payment_status')->label('Ödeme')->options(PaymentStatus::class),
                Filter::make('transfer_date')
                    ->schema([
                        DatePicker::make('from')->label('Transfer tarihi (başlangıç)')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Transfer tarihi (bitiş)')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)->columnSpan(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $d) => $query->whereHas('transfers', fn ($query) => $query->where('pickup_at', '>=', $d)))
                        ->when($data['until'] ?? null, fn ($query, $d) => $query->whereHas('transfers', fn ($query) => $query->where('pickup_at', '<=', $d.' 23:59:59')))),
                TrashedFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordUrl(fn (Reservation $r) => ReservationResource::getUrl('view', ['record' => $r]))
            ->recordActions([ViewAction::make()->iconButton(), EditAction::make()->iconButton()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()]),
            ]);
    }
}
