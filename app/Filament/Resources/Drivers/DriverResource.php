<?php

namespace App\Filament\Resources\Drivers;

use App\Enums\TransferStatus;
use App\Filament\Actions\GenerateApiTokenAction;
use App\Filament\Resources\Drivers\Pages\CreateDriver;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Models\Driver;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static string|\UnitEnum|null $navigationGroup = 'Filo';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Sürücü';

    protected static ?string $pluralModelLabel = 'Sürücüler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Ad Soyad')->required()->maxLength(120),
                TextInput::make('phone')->label('Telefon')->tel()->required()->maxLength(30),
                TextInput::make('email')->label('E-posta')->email()->maxLength(190),
                TextInput::make('license_number')->label('Ehliyet / SRC no')->maxLength(50),
                TagsInput::make('languages')->label('Diller')->placeholder('tr, en, de…')->suggestions(['tr', 'en', 'de', 'ru', 'fr', 'ar']),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Textarea::make('notes')->label('Notlar')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vehicle.vehicleType')->withCount([
                'transfers as today_count' => fn ($query) => $query->onDate(today())->where('status', '!=', TransferStatus::Cancelled),
            ]))
            ->columns([
                TextColumn::make('name')->label('Sürücü')->searchable()->weight('semibold')
                    ->description(fn (Driver $r) => $r->phone),
                TextColumn::make('vehicle.plate')->label('Araç')->badge()->color('gray')
                    ->description(fn (Driver $r) => $r->vehicle?->vehicleType?->name)
                    ->placeholder('Araç yok'),
                TextColumn::make('languages')->label('Diller')->badge()->formatStateUsing(fn ($state) => Str::upper($state)),
                TextColumn::make('today_count')->label('Bugünkü iş')->badge()
                    ->color(fn ($state) => $state ? 'primary' : 'gray')->alignCenter(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([TernaryFilter::make('is_active')->label('Aktif')])
            ->recordActions([
                Action::make('call')->label('Ara')->icon('heroicon-o-phone')->color('gray')
                    ->url(fn (Driver $r) => 'tel:'.preg_replace('/\s+/', '', $r->phone)),
                EditAction::make(),
                ActionGroup::make([GenerateApiTokenAction::make()]),
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrivers::route('/'),
            'create' => CreateDriver::route('/create'),
            'edit' => EditDriver::route('/{record}/edit'),
        ];
    }
}
