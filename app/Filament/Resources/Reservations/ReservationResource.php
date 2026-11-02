<?php

namespace App\Filament\Resources\Reservations;

use App\Enums\ReservationStatus;
use App\Filament\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Resources\Reservations\Pages\EditReservation;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Resources\Reservations\RelationManagers\TransfersRelationManager;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\ReservationsTable;
use App\Models\Reservation;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Rezervasyon';

    protected static ?string $pluralModelLabel = 'Rezervasyonlar';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return ReservationForm::edit($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [TransfersRelationManager::class];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Reservation::where('status', ReservationStatus::Pending)->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Onay bekleyen rezervasyon';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'passenger_name', 'passenger_phone', 'passenger_email', 'agency_reference'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Yolcu' => $record->passenger_name,
            'Tarih' => $record->outbound?->pickup_at?->format('d.m.Y H:i'),
            'Durum' => $record->status->getLabel(),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('outbound');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'create' => CreateReservation::route('/create'),
            'view' => ViewReservation::route('/{record}'),
            'edit' => EditReservation::route('/{record}/edit'),
        ];
    }
}
