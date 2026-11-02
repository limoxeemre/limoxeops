<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Support\FormQuote;
use App\Models\Agency;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Çağrı merkezi için hızlı fiyat: güzergah + tarih + kişi → tüm araç seçenekleri.
 * Seçilen teklif tek tıkla rezervasyon formuna aktarılır.
 */
class QuoteCalculator extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Fiyat Hesapla';

    protected static ?string $title = 'Fiyat Hesapla';

    protected string $view = 'filament.pages.quote-calculator';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'pickup_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'adults' => 2,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(['lg' => 2])
            ->components([
                ReservationForm::waypoint('pickup', 'Nereden', Heroicon::OutlinedMapPin),
                ReservationForm::waypoint('dropoff', 'Nereye', Heroicon::OutlinedFlag),
                ReservationForm::scheduleSection(withFlight: false),
                Section::make('Kişi & Acente')->icon(Heroicon::OutlinedUsers)->schema([
                    ReservationForm::paxGrid(),
                    Select::make('agency_id')->label('Acente fiyatı')->placeholder('Son kullanıcı fiyatı')
                        ->options(fn () => Agency::where('is_active', true)->pluck('name', 'id'))->live(),
                ]),
                Section::make('Teklifler')->icon(Heroicon::OutlinedSparkles)->columnSpanFull()->schema([
                    View::make('filament.components.quote-cards')->viewData(fn (Get $get) => [
                        'quote' => FormQuote::quote($get),
                        'payload' => FormQuote::payload($get),
                        'state' => $this->data,
                    ]),
                ]),
            ]);
    }
}
