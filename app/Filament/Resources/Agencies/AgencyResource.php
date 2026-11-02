<?php

namespace App\Filament\Resources\Agencies;

use App\Filament\Actions\GenerateApiTokenAction;
use App\Filament\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Resources\Agencies\Pages\EditAgency;
use App\Filament\Resources\Agencies\Pages\ListAgencies;
use App\Models\Agency;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'İş Ortakları';

    protected static ?string $modelLabel = 'Acente';

    protected static ?string $pluralModelLabel = 'Acenteler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Firma adı')->required()->maxLength(150),
                TextInput::make('contact_name')->label('Yetkili')->maxLength(120),
                TextInput::make('email')->label('E-posta')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Telefon')->tel(),
                TextInput::make('discount_percent')->label('İndirim / komisyon')->numeric()->minValue(0)->maxValue(100)->default(0)->suffix('%')
                    ->helperText('API ile alınan fiyatlara otomatik uygulanır.'),
                Toggle::make('is_active')->label('Aktif')->default(true)->inline(false),
                Textarea::make('notes')->label('Notlar')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Acente')->searchable()->weight('semibold')->description(fn (Agency $r) => $r->contact_name),
                TextColumn::make('email')->label('E-posta')->copyable()->color('gray'),
                TextColumn::make('discount_percent')->label('İndirim')->suffix('%')->alignCenter(),
                TextColumn::make('reservations_count')->label('Rezervasyon')->counts('reservations')->badge()->alignCenter(),
                TextColumn::make('tokens_count')->label('Token')->counts('tokens')->badge()->color('gray')->alignCenter(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([EditAction::make(), ActionGroup::make([GenerateApiTokenAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'),
            'create' => CreateAgency::route('/create'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
