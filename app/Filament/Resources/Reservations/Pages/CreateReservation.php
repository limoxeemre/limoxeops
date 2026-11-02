<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Enums\BookingSource;
use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Models\Agency;
use App\Services\Reservations\ReservationService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    protected static bool $canCreateAnother = false;

    public function form(Schema $schema): Schema
    {
        return ReservationForm::create($schema);
    }

    /** Fiyat Hesapla sayfasından gelen teklif bilgileriyle formu ön-doldur. */
    protected function fillForm(): void
    {
        parent::fillForm();

        $prefill = json_decode(base64_decode((string) request()->query('prefill')), true);
        if (is_array($prefill) && $prefill) {
            $this->form->fill([...$this->form->getRawState(), ...$prefill]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $point = fn (string $p) => array_filter([
            'location_id' => $data["{$p}_location_id"] ?? null,
            'lat' => $data["{$p}_lat"] ?? null,
            'lng' => $data["{$p}_lng"] ?? null,
            'address' => $data["{$p}_address"] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $roundTrip = (bool) ($data['round_trip'] ?? false);
        $source = BookingSource::from($data['source']);
        $agency = $source === BookingSource::Agency && ! empty($data['agency_id']) ? Agency::find($data['agency_id']) : null;

        $payload = [
            ...$data,
            'pickup' => $point('pickup'),
            'dropoff' => $point('dropoff'),
            'return_pickup_at' => $roundTrip ? ($data['return_pickup_at'] ?? null) : null,
            'return_flight_time' => $roundTrip ? ($data['return_flight_time'] ?? null) : null,
            'return_flight_number' => $roundTrip ? ($data['return_flight_number'] ?? null) : null,
            'extras' => collect($data['extras'] ?? [])
                ->map(fn ($e) => ['id' => $e['extra_id'], 'quantity' => $e['quantity'] ?? 1])->values()->all(),
        ];

        try {
            return app(ReservationService::class)->create(
                $payload,
                $source,
                $agency,
                auth()->user(),
                ! empty($data['manual_price_enabled']) ? (float) $data['manual_price'] : null,
            );
        } catch (ValidationException $e) {
            // Servis hatalarını form alanlarına bağla
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Rezervasyon oluşturuldu: '.$this->getRecord()->code;
    }
}
