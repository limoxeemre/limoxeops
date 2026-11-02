<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Models\Vehicle;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Filo';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Araç';

    protected static ?string $pluralModelLabel = 'Araçlar';

    protected static ?string $recordTitleAttribute = 'plate';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('plate')->label('Plaka')->required()->unique(ignoreRecord: true)->maxLength(20)
                    ->dehydrateStateUsing(fn ($state) => mb_strtoupper(trim($state))),
                Select::make('vehicle_type_id')->label('Araç tipi')->relationship('vehicleType', 'name')->required()->preload(),
                TextInput::make('brand_model')->label('Marka / Model')->maxLength(100),
                Select::make('driver_id')->label('Varsayılan sürücü')->relationship('driver', 'name')->searchable()->preload()
                    ->helperText('Atama ekranında bu sürücü seçilince araç otomatik gelir.'),
                TextInput::make('year')->label('Model yılı')->numeric()->minValue(1990)->maxValue(now()->year + 1),
                TextInput::make('color')->label('Renk')->maxLength(30),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('vehicleType', 'driver'))
            ->columns([
                TextColumn::make('plate')->label('Plaka')->searchable()->weight('bold')->fontFamily('mono'),
                TextColumn::make('brand_model')->label('Model')->description(fn (Vehicle $r) => trim($r->color.' '.$r->year)),
                TextColumn::make('vehicleType.name')->label('Tip')->badge()
                    ->description(fn (Vehicle $r) => $r->vehicleType->max_passengers.' kişi'),
                TextColumn::make('driver.name')->label('Sürücü')->placeholder('—'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([SelectFilter::make('vehicle_type_id')->label('Tip')->relationship('vehicleType', 'name')])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
