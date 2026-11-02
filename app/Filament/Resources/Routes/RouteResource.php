<?php

namespace App\Filament\Resources\Routes;

use App\Filament\Resources\Routes\Pages\CreateRoute;
use App\Filament\Resources\Routes\Pages\EditRoute;
use App\Filament\Resources\Routes\Pages\ListRoutes;
use App\Models\Route;
use App\Models\RoutePrice;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Geo\GeoMath;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * Rotalar & fiyat matrisi. Liste ekranında her araç tipi bir sütundur ve
 * fiyatlar tablo üzerinden doğrudan (Excel gibi) düzenlenebilir.
 */
class RouteResource extends Resource
{
    protected static ?string $model = Route::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Rota';

    protected static ?string $pluralModelLabel = 'Rotalar & Fiyatlar';

    public static function form(Schema $schema): Schema
    {
        $currency = config('transfer.currency');

        return $schema->columns(2)->components([
            Section::make('Güzergah')->columnSpan(1)->columns(2)->schema([
                Select::make('from_zone_id')->label('Nereden')->relationship('fromZone', 'name')->required()->searchable()->preload()->live(),
                Select::make('to_zone_id')->label('Nereye')->relationship('toZone', 'name')->required()->searchable()->preload()->live()
                    ->different('from_zone_id')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('from_zone_id', $get('from_zone_id')))
                    ->validationMessages(['unique' => 'Bu iki bölge arasında zaten bir rota var.']),
                Toggle::make('is_bidirectional')->label('Çift yönlü')->default(true)->columnSpanFull()
                    ->helperText('Açıksa aynı fiyat ters yönde de geçerlidir (Belek → Havalimanı).'),
                TextInput::make('distance_km')->label('Mesafe')->numeric()->suffix('km')
                    ->suffixAction(
                        Action::make('estimate')->icon('heroicon-o-sparkles')->tooltip('Bölge merkezlerinden tahmin et')
                            ->action(fn (Get $get, Set $set) => self::estimate($get, $set)),
                    ),
                TextInput::make('duration_min')->label('Süre')->numeric()->suffix('dk')
                    ->helperText('Havalimanı dönüşlerinde alış saati önerisinde kullanılır.'),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Araç Tipine Göre Fiyatlar')->columnSpan(1)->schema([
                Repeater::make('prices')->hiddenLabel()
                    ->relationship()
                    ->table([
                        TableColumn::make('Araç tipi'),
                        TableColumn::make('Fiyat ('.$currency.')')->width('10rem'),
                    ])
                    ->schema([
                        Select::make('vehicle_type_id')->options(fn () => VehicleType::orderBy('sort_order')->pluck('name', 'id'))
                            ->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('price')->numeric()->minValue(0)->required(),
                    ])
                    ->default(fn () => VehicleType::active()->orderBy('sort_order')->get()
                        ->map(fn ($vt) => ['vehicle_type_id' => $vt->id, 'price' => null])->all())
                    ->addActionLabel('Araç tipi ekle')
                    ->reorderable(false),
            ]),
        ]);
    }

    private static function estimate(Get $get, Set $set): void
    {
        $from = Zone::find($get('from_zone_id'));
        $to = Zone::find($get('to_zone_id'));

        if (! $from?->center_lat || ! $to?->center_lat) {
            Notification::make()->title('Önce iki bölgeyi de seçin')->warning()->send();

            return;
        }

        $km = round(GeoMath::haversineKm($from->center_lat, $from->center_lng, $to->center_lat, $to->center_lng)
            * config('transfer.pricing.road_factor'));
        $set('distance_km', $km);
        $set('duration_min', (int) ceil($km / config('transfer.pricing.average_speed_kmh') * 60));
    }

    public static function table(Table $table): Table
    {
        $vehicleTypes = VehicleType::active()->orderBy('sort_order')->get();
        $currency = config('transfer.currency');

        $priceColumns = $vehicleTypes->map(fn (VehicleType $vt) => TextInputColumn::make("price_{$vt->id}")
            ->label($vt->name)
            ->tooltip("{$vt->max_passengers} yolcu · {$vt->max_luggage} bagaj")
            ->type('number')
            ->rules(['nullable', 'numeric', 'min:0'])
            ->prefix($currency === 'EUR' ? '€' : $currency)
            ->extraInputAttributes(['style' => 'width: 5.5rem'])
            ->state(fn (Route $record) => $record->prices->firstWhere('vehicle_type_id', $vt->id)?->price)
            ->updateStateUsing(function (Route $record, $state) use ($vt) {
                if ($state === null || $state === '') {
                    RoutePrice::where(['route_id' => $record->id, 'vehicle_type_id' => $vt->id])->delete();
                } else {
                    RoutePrice::updateOrCreate(['route_id' => $record->id, 'vehicle_type_id' => $vt->id], ['price' => $state]);
                }

                return $state;
            }))->all();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('fromZone', 'toZone', 'prices'))
            ->description('Fiyatları doğrudan tablo üzerinde düzenleyebilirsiniz; hücreden çıkınca kaydedilir. Boş hücre = bu araç tipi bu rotada satılmaz (km tarifesi devreye girer).')
            ->columns([
                TextColumn::make('label')->label('Güzergah')->weight('semibold')
                    ->state(fn (Route $r) => $r->label)
                    ->description(fn (Route $r) => collect([$r->distance_km ? round($r->distance_km).' km' : null, $r->duration_min ? $r->duration_min.' dk' : null])->filter()->implode(' · '))
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('fromZone', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('toZone', fn ($query) => $query->where('name', 'like', "%{$search}%"))),
                ...$priceColumns,
            ])
            ->filters([
                SelectFilter::make('from_zone_id')->label('Nereden')->relationship('fromZone', 'name'),
                SelectFilter::make('to_zone_id')->label('Nereye')->relationship('toZone', 'name'),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                ReplicateAction::make()->iconButton()->label('Kopyala')
                    ->excludeAttributes(['to_zone_id'])
                    ->schema([Select::make('to_zone_id')->label('Yeni hedef bölge')->options(Zone::pluck('name', 'id'))->required()])
                    ->after(fn (Route $replica, Route $record) => $record->prices->each(
                        fn (RoutePrice $p) => $replica->prices()->create($p->only('vehicle_type_id', 'price'))
                    )),
                DeleteAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoutes::route('/'),
            'create' => CreateRoute::route('/create'),
            'edit' => EditRoute::route('/{record}/edit'),
        ];
    }
}
