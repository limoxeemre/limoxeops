<?php

namespace App\Filament\Resources\Locations;

use App\Enums\LocationType;
use App\Filament\Forms\Components\MapPicker;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use App\Services\Geo\ZoneResolver;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Lokasyon';

    protected static ?string $pluralModelLabel = 'Lokasyonlar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Lokasyon')->columnSpan(1)->schema([
                TextInput::make('name')->label('Ad')->required()->maxLength(150)->placeholder('Otel adı, terminal…'),
                Select::make('type')->label('Tür')->options(LocationType::class)->required()->default(LocationType::Hotel)->live(),
                TextInput::make('iata_code')->label('IATA kodu')->maxLength(3)
                    ->visible(fn (Get $get) => in_array($get('type'), [LocationType::Airport, LocationType::Airport->value], true)),
                TextInput::make('address')->label('Adres')->maxLength(255),
                Select::make('zone_id')->label('Bölge')->relationship('zone', 'name')->placeholder('Koordinattan otomatik')
                    ->helperText('Boş bırakılırsa haritadaki konuma göre otomatik atanır.'),
                Text::make(fn (Get $get) => self::zoneHint($get('lat'), $get('lng'))),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Konum')->columnSpan(2)->schema([
                MapPicker::make('point')->hiddenLabel()->height(420)
                    ->latLngFields('lat', 'lng')
                    ->addressField('address'),
                Hidden::make('lat')->required()->validationMessages(['required' => 'Haritadan konum seçin.']),
                Hidden::make('lng')->required(),
            ]),
        ]);
    }

    private static function zoneHint($lat, $lng): string
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return 'Haritadan konum seçin.';
        }

        $zone = app(ZoneResolver::class)->resolve((float) $lat, (float) $lng);

        return $zone ? "📍 Tespit edilen bölge: {$zone->name}" : '⚠️ Bu nokta hiçbir fiyat bölgesinde değil (km bazlı fiyat uygulanır).';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('zone'))
            ->columns([
                TextColumn::make('name')->label('Lokasyon')->searchable()->weight('semibold')
                    ->icon(fn (Location $r) => $r->type->getIcon())
                    ->description(fn (Location $r) => $r->address !== $r->name ? $r->address : null),
                TextColumn::make('type')->label('Tür')->badge()->color('gray'),
                TextColumn::make('zone.name')->label('Bölge')->badge()->placeholder('Bölge dışı')
                    ->color(fn (Location $r) => $r->zone ? 'primary' : 'danger'),
                TextColumn::make('coords')->label('Koordinat')->state(fn (Location $r) => number_format($r->lat, 5).', '.number_format($r->lng, 5))
                    ->fontFamily('mono')->size('xs')->color('gray')
                    ->url(fn (Location $r) => "https://www.google.com/maps?q={$r->lat},{$r->lng}", true),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tür')->options(LocationType::class),
                SelectFilter::make('zone_id')->label('Bölge')->relationship('zone', 'name'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }
}
