<?php

namespace App\Filament\Resources\PricingRules;

use App\Enums\AdjustmentType;
use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\PricingRules\Pages\ListPricingRules;
use App\Models\PricingRule;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Fiyatlandırma';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Fiyat Kuralı';

    protected static ?string $pluralModelLabel = 'Fiyat Kuralları';

    public const DAYS = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Kural')->columnSpan(1)->schema([
                TextInput::make('name')->label('Kural adı')->required()->placeholder('Gece tarifesi'),
                ToggleButtons::make('adjustment_type')->label('Tür')->options(AdjustmentType::class)->inline()->required()
                    ->default(AdjustmentType::Percent)->live(),
                TextInput::make('value')->label('Değer')->numeric()->required()
                    ->prefix(fn (Get $get) => self::isPercent($get('adjustment_type')) ? '%' : config('transfer.currency'))
                    ->helperText('Pozitif değer zam, negatif değer indirimdir. Yüzde, rota taban fiyatı üzerinden hesaplanır.'),
                TextInput::make('priority')->label('Öncelik')->numeric()->default(0),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]),
            Section::make('Koşullar')->description('Boş bırakılan koşullar her zaman geçerli sayılır.')->columnSpan(1)->columns(2)->schema([
                DatePicker::make('starts_on')->label('Başlangıç tarihi')->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('ends_on')->label('Bitiş tarihi')->native(false)->displayFormat('d.m.Y')->afterOrEqual('starts_on'),
                TimePicker::make('time_from')->label('Saat (başlangıç)')->seconds(false),
                TimePicker::make('time_to')->label('Saat (bitiş)')->seconds(false)->helperText('Gece yarısını geçebilir: 22:00 → 06:00'),
                CheckboxList::make('days_of_week')->label('Günler')->options(self::DAYS)->columns(7)->columnSpanFull(),
                Select::make('vehicle_type_id')->label('Sadece araç tipi')->relationship('vehicleType', 'name')->placeholder('Tümü'),
                Select::make('zone_id')->label('Sadece bölge (alış/bırakış)')->relationship('zone', 'name')->placeholder('Tümü')->searchable()->preload(),
            ]),
        ]);
    }

    private static function isPercent($state): bool
    {
        return ($state instanceof AdjustmentType ? $state : AdjustmentType::tryFrom((string) $state)) === AdjustmentType::Percent;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('vehicleType', 'zone'))
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('name')->label('Kural')->weight('semibold')->searchable(),
                TextColumn::make('value')->label('Etki')->badge()
                    ->formatStateUsing(fn (PricingRule $r) => $r->description)
                    ->color(fn (PricingRule $r) => (float) $r->value >= 0 ? 'warning' : 'success'),
                TextColumn::make('conditions')->label('Koşullar')->state(fn (PricingRule $r) => self::summary($r))->wrap()->color('gray'),
                TextColumn::make('priority')->label('Öncelik')->alignCenter()->sortable(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make(), ReplicateAction::make()->label('Kopyala'), DeleteAction::make()]);
    }

    private static function summary(PricingRule $r): string
    {
        $parts = [];
        if ($r->starts_on || $r->ends_on) {
            $parts[] = '📅 '.($r->starts_on?->format('d.m.Y') ?? '…').' – '.($r->ends_on?->format('d.m.Y') ?? '…');
        }
        if ($r->time_from && $r->time_to) {
            $parts[] = '🕒 '.substr($r->time_from, 0, 5).'–'.substr($r->time_to, 0, 5);
        }
        if ($r->days_of_week) {
            $parts[] = implode(', ', array_map(fn ($d) => self::DAYS[(int) $d] ?? $d, $r->days_of_week));
        }
        if ($r->vehicle_type_id) {
            $parts[] = '🚐 '.$r->vehicleType?->name;
        }
        if ($r->zone_id) {
            $parts[] = '📍 '.$r->zone?->name;
        }

        return $parts ? implode(' · ', $parts) : 'Her zaman';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricingRules::route('/'),
            'create' => CreatePricingRule::route('/create'),
            'edit' => EditPricingRule::route('/{record}/edit'),
        ];
    }
}
