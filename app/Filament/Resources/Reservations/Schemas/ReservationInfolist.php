<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Filament\Forms\Components\ZonePolygonEditor;
use App\Models\Reservation;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReservationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                Section::make('Yolcu')->icon(Heroicon::OutlinedUser)->columns(['md' => 3])->schema([
                    TextEntry::make('passenger_name')->label('Ad Soyad')->weight('semibold'),
                    TextEntry::make('passenger_phone')->label('Telefon')->icon('heroicon-m-phone')->copyable()
                        ->url(fn (Reservation $r) => 'tel:'.preg_replace('/[^0-9+]/', '', $r->passenger_phone)),
                    TextEntry::make('passenger_email')->label('E-posta')->icon('heroicon-m-envelope')->copyable(),
                    TextEntry::make('passenger_country')->label('Ülke')
                        ->formatStateUsing(fn ($state) => ReservationForm::COUNTRIES[$state] ?? $state)->placeholder('—'),
                    TextEntry::make('pax')->label('Kişi')
                        ->state(fn (Reservation $r) => "{$r->adults} yetişkin · {$r->children} çocuk · {$r->infants} bebek"),
                    TextEntry::make('luggage')->label('Bagaj')->suffix(' parça'),
                    TextEntry::make('customer_notes')->label('Müşteri notu')->placeholder('—')->columnSpanFull(),
                ]),
                Section::make('Güzergah')->icon(Heroicon::OutlinedMap)->collapsible()->schema([
                    ViewEntry::make('map')->hiddenLabel()->view('filament.components.reservation-map')
                        ->viewData(fn (Reservation $record) => self::mapData($record)),
                ]),
            ]),
            Group::make()->columnSpan(['lg' => 1])->schema([
                Section::make('Özet')->icon(Heroicon::OutlinedClipboardDocumentCheck)->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('status')->label('Durum')->badge(),
                        TextEntry::make('trip_type')->label('Tip')->badge()->color('gray'),
                        TextEntry::make('source')->label('Kaynak')->badge(),
                        TextEntry::make('vehicleType.name')->label('Araç')->badge()->color('gray'),
                    ]),
                    TextEntry::make('agency.name')->label('Acente')->placeholder('—')
                        ->suffix(fn (Reservation $r) => $r->agency_reference ? " · Ref: {$r->agency_reference}" : null)
                        ->visible(fn (Reservation $r) => $r->agency_id),
                    TextEntry::make('created_at')->label('Oluşturma')->dateTime('d.m.Y H:i'),
                    TextEntry::make('cancel_reason')->label('İptal nedeni')->color('danger')
                        ->visible(fn (Reservation $r) => $r->cancelled_at),
                    TextEntry::make('internal_notes')->label('İç not')->placeholder('—')->color('warning'),
                ]),
                Section::make('Fiyat & Ödeme')->icon(Heroicon::OutlinedBanknotes)->schema([
                    ViewEntry::make('breakdown')->hiddenLabel()->view('filament.components.stored-price')
                        ->viewData(fn (Reservation $record) => ['r' => $record->loadMissing('extras.extra', 'transfers')]),
                    Grid::make(2)->schema([
                        TextEntry::make('payment_status')->label('Ödeme')->badge(),
                        TextEntry::make('payment_method')->label('Yöntem')->placeholder('—'),
                    ]),
                ]),
            ]),
        ]);
    }

    private static function mapData(Reservation $r): array
    {
        $t = $r->transfers->firstWhere('direction.value', 'outbound') ?? $r->transfers->first();

        return [
            'zones' => ZonePolygonEditor::zonesForMap(),
            'markers' => $t ? [
                ['lat' => $t->pickup_lat, 'lng' => $t->pickup_lng, 'label' => 'Alış: '.$t->pickup_address, 'color' => '#16a34a', 'size' => 9],
                ['lat' => $t->dropoff_lat, 'lng' => $t->dropoff_lng, 'label' => 'Bırakış: '.$t->dropoff_address, 'color' => '#dc2626', 'size' => 9],
            ] : [],
            'line' => $t ? [[$t->pickup_lat, $t->pickup_lng], [$t->dropoff_lat, $t->dropoff_lng]] : null,
        ];
    }
}
