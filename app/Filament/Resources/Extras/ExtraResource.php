<?php

namespace App\Filament\Resources\Extras;

use App\Filament\Resources\Extras\Pages\CreateExtra;
use App\Filament\Resources\Extras\Pages\EditExtra;
use App\Filament\Resources\Extras\Pages\ListExtras;
use App\Models\Extra;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ExtraResource extends Resource
{
    protected static ?string $model = Extra::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'Ekstra Hizmet';

    protected static ?string $pluralModelLabel = 'Ekstra Hizmetler';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Ad')->required()->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set, $get) => $get('code') ? null : $set('code', Str::upper(Str::slug($state, '_')))),
                TextInput::make('code')->label('Kod')->required()->maxLength(30)->unique(ignoreRecord: true)
                    ->helperText('API\'de kullanılır, ör. BABY_SEAT'),
                TextInput::make('description')->label('Açıklama')->columnSpanFull()->maxLength(255),
                TextInput::make('price')->label('Fiyat')->numeric()->minValue(0)->required()->suffix(config('transfer.currency')),
                TextInput::make('max_quantity')->label('Maks. adet')->numeric()->minValue(1)->default(1)->required(),
                Toggle::make('per_leg')->label('Her bacak için ücretlendir')->default(true)
                    ->helperText('Gidiş-dönüş rezervasyonda iki kez ücretlendirilir (ör. bebek koltuğu).'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad')->searchable()->weight('semibold')->description(fn (Extra $r) => $r->description),
                TextColumn::make('code')->label('Kod')->badge()->color('gray')->copyable(),
                TextColumn::make('price')->label('Fiyat')->money(config('transfer.currency'))->sortable(),
                IconColumn::make('per_leg')->label('Bacak başı')->boolean(),
                TextColumn::make('max_quantity')->label('Maks.')->alignCenter(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExtras::route('/'),
            'create' => CreateExtra::route('/create'),
            'edit' => EditExtra::route('/{record}/edit'),
        ];
    }
}
