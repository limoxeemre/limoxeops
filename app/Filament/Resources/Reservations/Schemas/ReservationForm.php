<?php

namespace App\Filament\Resources\Reservations\Schemas;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Filament\Forms\Components\MapPicker;
use App\Filament\Support\FormQuote;
use App\Models\Extra;
use App\Models\Location;
use App\Models\VehicleType;
use App\Services\Geo\Waypoint;
use App\Services\Geo\ZoneResolver;
use App\Services\Pricing\PriceCalculator;
use App\Services\Reservations\PickupTimeSuggester;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class ReservationForm
{
    public const COUNTRIES = [
        'TR' => '🇹🇷 Türkiye', 'DE' => '🇩🇪 Almanya', 'GB' => '🇬🇧 Birleşik Krallık', 'RU' => '🇷🇺 Rusya',
        'NL' => '🇳🇱 Hollanda', 'PL' => '🇵🇱 Polonya', 'UA' => '🇺🇦 Ukrayna', 'AT' => '🇦🇹 Avusturya',
        'CH' => '🇨🇭 İsviçre', 'BE' => '🇧🇪 Belçika', 'FR' => '🇫🇷 Fransa', 'SE' => '🇸🇪 İsveç',
        'NO' => '🇳🇴 Norveç', 'DK' => '🇩🇰 Danimarka', 'KZ' => '🇰🇿 Kazakistan', 'IL' => '🇮🇱 İsrail', 'US' => '🇺🇸 ABD',
    ];

    /** Yeni rezervasyon (telefon / ofis satışı) formu */
    public static function create(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                Grid::make(['md' => 2])->schema([
                    self::waypoint('pickup', 'Alış Noktası', Heroicon::OutlinedMapPin),
                    self::waypoint('dropoff', 'Bırakış Noktası', Heroicon::OutlinedFlag),
                ]),
                self::scheduleSection(),
                self::passengerSection(),
                self::vehicleSection(),
            ]),
            Group::make()->columnSpan(['lg' => 1])->schema([
                Section::make('Fiyat Özeti')->icon(Heroicon::OutlinedCalculator)->schema([
                    View::make('filament.components.price-summary')
                        ->viewData(fn (Get $get) => [
                            'quote' => $quote = FormQuote::quote($get),
                            'option' => $quote && $get('vehicle_type_id') ? $quote->forVehicleType((int) $get('vehicle_type_id')) : null,
                            'hasInput' => FormQuote::payload($get) !== null,
                        ]),
                    Toggle::make('manual_price_enabled')->label('Manuel fiyat uygula')->live()
                        ->helperText('Tarifede olmayan güzergah veya özel anlaşma için.'),
                    TextInput::make('manual_price')->label('Toplam fiyat')->numeric()->minValue(0)
                        ->prefix(config('transfer.currency'))
                        ->visible(fn (Get $get) => $get('manual_price_enabled'))
                        ->required(fn (Get $get) => $get('manual_price_enabled')),
                ]),
                self::bookingSection(),
            ]),
        ]);
    }

    /** Düzenleme: güzergah/saat bilgileri transfer bacaklarında düzenlenir, burada rezervasyon seviyesi alanlar var. */
    public static function edit(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                self::passengerSection(withPriceRefresh: false),
                Section::make('Notlar')->columns(2)->schema([
                    Textarea::make('customer_notes')->label('Müşteri notu')->rows(3),
                    Textarea::make('internal_notes')->label('Operasyon notu (iç)')->rows(3),
                ]),
            ]),
            Group::make()->columnSpan(['lg' => 1])->schema([
                Section::make('Durum & Ödeme')->schema([
                    Select::make('status')->label('Rezervasyon durumu')->options(ReservationStatus::class)->required()->native(false),
                    Select::make('payment_status')->label('Ödeme durumu')->options(PaymentStatus::class)->required()->native(false),
                    Select::make('payment_method')->label('Ödeme yöntemi')->options(PaymentMethod::class)->native(false),
                    TextInput::make('total_price')->label('Toplam fiyat')->numeric()->prefix(fn ($record) => $record?->currency)
                        ->helperText('Değiştirmek fiyat dökümünü güncellemez; bacak fiyatlarını ayrıca düzenleyin.'),
                ]),
                Section::make('Kaynak')->schema([
                    Select::make('agency_id')->label('Acente')->relationship('agency', 'name')->searchable()->preload(),
                    TextInput::make('agency_reference')->label('Acente referans no'),
                ]),
            ]),
        ]);
    }

    public static function waypoint(string $p, string $title, Heroicon $icon): Section
    {
        return Section::make($title)->icon($icon)->compact()->schema([
            Select::make("{$p}_location_id")
                ->label('Kayıtlı lokasyon')
                ->placeholder('Havalimanı, otel… veya haritadan seçin')
                ->options(fn () => Location::active()->orderBy('name')->get()
                    ->groupBy(fn (Location $l) => $l->type->getLabel())
                    ->map(fn ($group) => $group->pluck('name', 'id'))
                    ->sortKeys()
                    ->all())
                ->getOptionLabelUsing(fn ($value) => Location::find($value)?->name)
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) use ($p) {
                    if ($location = Location::find($state)) {
                        $set("{$p}_address", $location->address ?: $location->name);
                        $set("{$p}_lat", $location->lat);
                        $set("{$p}_lng", $location->lng);
                        $set("{$p}_point", ['lat' => $location->lat, 'lng' => $location->lng]);
                    }
                }),
            TextInput::make("{$p}_address")->label('Adres / Otel adı')->required()->maxLength(255)
                ->live(onBlur: true),
            MapPicker::make("{$p}_point")->hiddenLabel()->height(240)
                ->latLngFields("{$p}_lat", "{$p}_lng")
                ->addressField("{$p}_address")
                // Haritadan serbest nokta seçildiyse kayıtlı lokasyon bağlantısını kaldır
                ->afterStateUpdated(fn (Set $set) => $set("{$p}_location_id", null)),
            Hidden::make("{$p}_lat")->required()->validationMessages(['required' => 'Lokasyon seçin veya haritadan nokta işaretleyin.']),
            Hidden::make("{$p}_lng")->required(),
            Text::make(fn (Get $get) => self::zoneBadge($get("{$p}_lat"), $get("{$p}_lng"), $get("{$p}_location_id"))),
        ]);
    }

    private static function zoneBadge($lat, $lng, $locationId): HtmlString
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return new HtmlString('<span class="tr-badge info">Konum seçilmedi</span>');
        }

        $zone = $locationId
            ? Location::with('zone')->find($locationId)?->zone
            : app(ZoneResolver::class)->resolve((float) $lat, (float) $lng);

        return new HtmlString($zone
            ? '<span class="tr-badge success">📍 Bölge: '.e($zone->name).'</span>'
            : '<span class="tr-badge warning">⚠️ Bölge dışı — km bazlı fiyat</span>');
    }

    public static function scheduleSection(bool $withFlight = true): Section
    {
        $dt = fn (string $name, string $label) => DateTimePicker::make($name)->label($label)
            ->native(false)->seconds(false)->minutesStep(5)->displayFormat('d.m.Y H:i')->firstDayOfWeek(1)
            ->live(onBlur: true);

        return Section::make('Tarih & Uçuş')->icon(Heroicon::OutlinedCalendarDays)->schema([
            Grid::make(['md' => 3])->schema([
                TextInput::make('flight_number')->label('Uçuş no')->placeholder('TK2412')->maxLength(15)->visible($withFlight)
                    ->dehydrateStateUsing(fn ($state) => $state ? strtoupper(str_replace(' ', '', $state)) : null),
                $dt('flight_time', 'Uçuş saati (iniş/kalkış)'),
                $dt('pickup_at', 'Araç alış saati')->required()
                    ->suffixAction(self::suggestTimeAction('pickup', 'dropoff', 'flight_time', 'pickup_at')),
            ]),
            Toggle::make('round_trip')->label('Gidiş - dönüş')->live()->inline(),
            Grid::make(['md' => 3])->visible(fn (Get $get) => $get('round_trip'))->schema([
                TextInput::make('return_flight_number')->label('Dönüş uçuş no')->maxLength(15)->visible($withFlight)
                    ->dehydrateStateUsing(fn ($state) => $state ? strtoupper(str_replace(' ', '', $state)) : null),
                $dt('return_flight_time', 'Dönüş uçuş saati'),
                $dt('return_pickup_at', 'Dönüş alış saati')
                    ->required(fn (Get $get) => $get('round_trip'))
                    ->after('pickup_at')
                    ->suffixAction(self::suggestTimeAction('dropoff', 'pickup', 'return_flight_time', 'return_pickup_at')),
            ]),
        ]);
    }

    /** Uçuş saatinden alış saati önerisi (havalimanına gidişte uçuş − yol süresi − tampon). */
    private static function suggestTimeAction(string $from, string $to, string $flightField, string $target): Action
    {
        return Action::make("suggest_{$target}")
            ->icon(Heroicon::OutlinedSparkles)
            ->tooltip('Uçuş saatine göre öner')
            ->action(function (Get $get, Set $set) use ($from, $to, $flightField, $target) {
                $payload = FormQuote::payload($get) ?? [];
                if (! $get($flightField) || empty($payload[$from]) || empty($payload[$to])) {
                    Notification::make()->title('Önce alış/bırakış noktası ve uçuş saatini girin')->warning()->send();

                    return;
                }

                $calc = app(PriceCalculator::class);
                $a = Waypoint::fromArray($payload[$from]);
                $b = Waypoint::fromArray($payload[$to]);
                $duration = $calc->findRoute($a->zone, $b->zone)?->duration_min ?? $calc->estimateDuration($calc->roadDistance($a, $b));

                $time = app(PickupTimeSuggester::class)->suggest($a, $b, CarbonImmutable::parse($get($flightField)), $duration);
                $set($target, $time->format('Y-m-d H:i:s'));

                Notification::make()
                    ->title('Alış saati: '.$time->format('d.m.Y H:i'))
                    ->body($b->isAirport() ? "Yol ~{$duration} dk + havalimanı tamponu dahil." : 'İniş saatine göre ayarlandı.')
                    ->success()->send();
            });
    }

    private static function passengerSection(bool $withPriceRefresh = true): Section
    {
        return Section::make('Yolcu')->icon(Heroicon::OutlinedUser)->schema([
            Grid::make(['md' => 2])->schema([
                TextInput::make('passenger_name')->label('Ad Soyad')->required()->maxLength(120),
                TextInput::make('passenger_phone')->label('Telefon')->tel()->required()->maxLength(30)->placeholder('+44 7700 900123'),
                TextInput::make('passenger_email')->label('E-posta')->email()->required()->maxLength(190),
                Select::make('passenger_country')->label('Ülke')->options(self::COUNTRIES)->searchable(),
            ]),
            self::paxGrid($withPriceRefresh),
        ]);
    }

    public static function paxGrid(bool $live = true): Grid
    {
        $count = fn (string $name, string $label, int $min, string $icon) => TextInput::make($name)->label($label)
            ->numeric()->minValue($min)->maxValue(60)->default($min)->required()->prefixIcon($icon)
            ->live(onBlur: $live);

        return Grid::make(['default' => 2, 'md' => 4])->schema([
            $count('adults', 'Yetişkin', 1, 'heroicon-o-user'),
            $count('children', 'Çocuk (2-12)', 0, 'heroicon-o-face-smile'),
            $count('infants', 'Bebek (0-2)', 0, 'heroicon-o-heart'),
            $count('luggage', 'Bagaj', 0, 'heroicon-o-briefcase'),
        ]);
    }

    private static function vehicleSection(): Section
    {
        return Section::make('Araç & Ekstralar')->icon(Heroicon::OutlinedTruck)->schema([
            Radio::make('vehicle_type_id')
                ->label('Araç tipi')
                ->required()
                ->live()
                ->options(function (Get $get) {
                    $quote = FormQuote::quote($get);

                    return VehicleType::active()->orderBy('sort_order')->get()->mapWithKeys(function (VehicleType $vt) use ($quote) {
                        $option = $quote?->forVehicleType($vt->id);
                        $price = $option ? ' — '.FormQuote::money($option->total) : '';

                        return [$vt->id => new HtmlString('<strong>'.e($vt->name).'</strong>'.$price)];
                    })->all();
                })
                ->descriptions(function (Get $get) {
                    $quote = FormQuote::quote($get);
                    $pax = (int) $get('adults') + (int) $get('children') + (int) $get('infants');

                    return VehicleType::active()->orderBy('sort_order')->get()->mapWithKeys(function (VehicleType $vt) use ($quote, $pax, $get) {
                        $cap = "{$vt->max_passengers} yolcu · {$vt->max_luggage} bagaj";
                        if ($vt->max_passengers < $pax || $vt->max_luggage < (int) $get('luggage')) {
                            return [$vt->id => $cap.' · kapasite yetersiz'];
                        }
                        if ($quote && ! $quote->forVehicleType($vt->id)) {
                            return [$vt->id => $cap.' · bu güzergahta tarife yok'];
                        }

                        return [$vt->id => $cap.($vt->description ? ' · '.$vt->description : '')];
                    })->all();
                })
                ->disableOptionWhen(function (string $value, Get $get) {
                    if ($get('manual_price_enabled')) {
                        return false;
                    }
                    $quote = FormQuote::quote($get);

                    return $quote !== null && ! $quote->forVehicleType((int) $value);
                })
                ->columns(['md' => 2]),

            Repeater::make('extras')->label('Ekstralar')
                ->table([
                    TableColumn::make('Ekstra'),
                    TableColumn::make('Adet')->width('7rem'),
                ])
                ->schema([
                    Select::make('extra_id')
                        ->options(fn () => Extra::active()->get()->mapWithKeys(fn (Extra $e) => [
                            $e->id => $e->name.' ('.FormQuote::money($e->price).($e->per_leg ? '/yön' : '').')',
                        ]))
                        ->required()->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems()->live(),
                    TextInput::make('quantity')->numeric()->minValue(1)->maxValue(5)->default(1)->required()->live(onBlur: true),
                ])
                ->defaultItems(0)
                ->addActionLabel('Ekstra ekle')
                ->reorderable(false)
                ->live(),
        ]);
    }

    private static function bookingSection(): Section
    {
        return Section::make('Kayıt Bilgileri')->icon(Heroicon::OutlinedClipboardDocumentList)->schema([
            ToggleButtons::make('source')->label('Kaynak')->inline()->required()
                ->options([BookingSource::Phone->value => 'Telefon', BookingSource::Admin->value => 'Ofis', BookingSource::Agency->value => 'Acente'])
                ->default(BookingSource::Phone->value)->live(),
            Select::make('agency_id')->label('Acente')->relationship('agency', 'name', fn ($query) => $query->where('is_active', true))
                ->searchable()->preload()->live()
                ->visible(fn (Get $get) => $get('source') === BookingSource::Agency->value)
                ->required(fn (Get $get) => $get('source') === BookingSource::Agency->value),
            TextInput::make('agency_reference')->label('Acente referans no')
                ->visible(fn (Get $get) => $get('source') === BookingSource::Agency->value),
            ToggleButtons::make('status')->label('Durum')->inline()->required()
                ->options([ReservationStatus::Confirmed->value => 'Onaylı', ReservationStatus::Pending->value => 'Opsiyon'])
                ->colors([ReservationStatus::Confirmed->value => 'success', ReservationStatus::Pending->value => 'warning'])
                ->default(ReservationStatus::Confirmed->value),
            Select::make('payment_method')->label('Ödeme yöntemi')->options(PaymentMethod::class)->default(PaymentMethod::CashToDriver)->native(false),
            Select::make('payment_status')->label('Ödeme durumu')->options(PaymentStatus::class)->default(PaymentStatus::Unpaid)->native(false),
            Textarea::make('customer_notes')->label('Müşteri notu')->rows(2)->placeholder('Sürücü de görür'),
            Textarea::make('internal_notes')->label('İç not')->rows(2),
        ]);
    }
}
