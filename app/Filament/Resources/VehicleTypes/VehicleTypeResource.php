<?php

namespace App\Filament\Resources\VehicleTypes;

use App\Filament\Resources\VehicleTypes\Pages\CreateVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\EditVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\ListVehicleTypes;
use App\Models\VehicleType;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VehicleTypeResource extends Resource
{
    protected static ?string $model = VehicleType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Araç Tipi';

    protected static ?string $pluralModelLabel = 'Araç Tipleri';

    public static function form(Schema $schema): Schema
    {
        $currency = config('transfer.currency');

        return $schema->columns(3)->components([
            Section::make('Araç Tipi')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Ad')->required()->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ? null : $set('slug', Str::slug($state))),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(100),
                TextInput::make('description')->label('Açıklama')->placeholder('Mercedes Vito veya benzeri')->columnSpanFull(),
                TextInput::make('max_passengers')->label('Maks. yolcu')->numeric()->minValue(1)->required()->prefixIcon('heroicon-o-users'),
                TextInput::make('max_luggage')->label('Maks. bagaj')->numeric()->minValue(0)->required()->prefixIcon('heroicon-o-briefcase'),
            ]),
            Section::make('Görsel & Durum')->columnSpan(1)->schema([
                FileUpload::make('image')->label('Görsel')->image()->disk('public')->directory('vehicle-types')->imageEditor(),
                TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Km Bazlı Yedek Tarife')
                ->description('Sabit rota fiyatı tanımlı olmayan güzergahlarda kullanılır. Km ücreti 0 ise bu araç yalnızca sabit rotalarda satılır.')
                ->columnSpan(2)->columns(3)
                ->schema([
                    TextInput::make('base_fare')->label('Açılış ücreti')->numeric()->minValue(0)->default(0)->suffix($currency),
                    TextInput::make('per_km_rate')->label('Km ücreti')->numeric()->minValue(0)->default(0)->suffix($currency.'/km'),
                    TextInput::make('min_fare')->label('Minimum ücret')->numeric()->minValue(0)->default(0)->suffix($currency),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->imageHeight(40)->defaultImageUrl(null),
                TextColumn::make('name')->label('Ad')->weight('semibold')->description(fn (VehicleType $r) => $r->description)->searchable(),
                TextColumn::make('max_passengers')->label('Yolcu')->icon('heroicon-o-users')->alignCenter(),
                TextColumn::make('max_luggage')->label('Bagaj')->icon('heroicon-o-briefcase')->alignCenter(),
                TextColumn::make('per_km_rate')->label('Km tarifesi')
                    ->formatStateUsing(fn (VehicleType $r) => $r->hasDistanceTariff()
                        ? sprintf('%s + %s/km (min %s)', (float) $r->base_fare, (float) $r->per_km_rate, (float) $r->min_fare)
                        : 'Yok')
                    ->color(fn (VehicleType $r) => $r->hasDistanceTariff() ? null : 'gray'),
                TextColumn::make('vehicles_count')->label('Araç')->counts('vehicles')->badge(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleTypes::route('/'),
            'create' => CreateVehicleType::route('/create'),
            'edit' => EditVehicleType::route('/{record}/edit'),
        ];
    }
}
