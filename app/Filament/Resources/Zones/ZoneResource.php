<?php

namespace App\Filament\Resources\Zones;

use App\Enums\ZoneType;
use App\Filament\Forms\Components\ZonePolygonEditor;
use App\Filament\Resources\Zones\Pages\CreateZone;
use App\Filament\Resources\Zones\Pages\EditZone;
use App\Filament\Resources\Zones\Pages\ListZones;
use App\Models\Zone;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Bölge';

    protected static ?string $pluralModelLabel = 'Bölgeler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Bölge Bilgileri')->columnSpan(1)->schema([
                TextInput::make('name')->label('Ad')->required()->maxLength(100),
                TextInput::make('code')->label('Kod')->required()->maxLength(20)->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn ($state) => strtoupper($state))->placeholder('LARA'),
                Select::make('type')->label('Tür')->options(ZoneType::class)->required()->default(ZoneType::Resort),
                ColorPicker::make('color')->label('Harita rengi')->default('#3b82f6')->live(),
                TextInput::make('priority')->label('Öncelik')->numeric()->default(0)
                    ->helperText('Çakışan bölgelerde yüksek öncelik kazanır. Havalimanı poligonlarına yüksek değer verin.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Sınır')
                ->description('Bu poligonun içindeki tüm adresler bu bölgenin fiyatını alır.')
                ->columnSpan(2)
                ->schema([
                    ZonePolygonEditor::make('polygon')->hiddenLabel()
                        ->color(fn (Get $get) => $get('color') ?: '#3b82f6')
                        ->rules(['nullable', 'array'])
                        ->requiredWithout('radius_km')
                        ->validationMessages(['required_without' => 'Haritada bir sınır çizin veya yarıçap tanımlayın.']),
                ]),
            Section::make('Alternatif: Merkez + Yarıçap')
                ->description('Poligon çizilmezse bölge, merkezden bu yarıçap içindeki noktaları kapsar.')
                ->columnSpanFull()->columns(3)->collapsed()
                ->schema([
                    TextInput::make('center_lat')->label('Merkez enlem')->numeric(),
                    TextInput::make('center_lng')->label('Merkez boylam')->numeric(),
                    TextInput::make('radius_km')->label('Yarıçap')->numeric()->suffix('km'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('name')->label('Bölge')->searchable()->weight('semibold')->description(fn (Zone $z) => $z->code),
                TextColumn::make('type')->label('Tür')->badge(),
                TextColumn::make('locations_count')->label('Lokasyon')->counts('locations')->alignCenter(),
                TextColumn::make('outgoing_routes_count')->label('Rota')->counts('outgoingRoutes')->alignCenter()
                    ->tooltip('Bu bölgeden başlayan rota sayısı'),
                TextColumn::make('polygon')->label('Sınır')
                    ->state(fn (Zone $z) => $z->polygon ? count($z->polygon).' köşe' : ($z->radius_km ? $z->radius_km.' km yarıçap' : 'Tanımsız'))
                    ->color(fn (Zone $z) => $z->polygon || $z->radius_km ? 'gray' : 'danger'),
                TextColumn::make('priority')->label('Öncelik')->sortable()->alignCenter(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->filters([SelectFilter::make('type')->label('Tür')->options(ZoneType::class)])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListZones::route('/'),
            'create' => CreateZone::route('/create'),
            'edit' => EditZone::route('/{record}/edit'),
        ];
    }
}
