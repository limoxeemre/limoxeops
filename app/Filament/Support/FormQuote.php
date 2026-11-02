<?php

namespace App\Filament\Support;

use App\Models\Agency;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\Quote;
use App\Services\Reservations\ReservationService;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

/**
 * Panel formlarındaki (rezervasyon oluştur, fiyat hesapla) state'ten canlı teklif üretir.
 * Aynı istek içinde aynı state için tek kez hesaplar.
 */
class FormQuote
{
    private static array $memo = [];

    public static function payload(Get $get): ?array
    {
        $point = fn (string $p) => $get("{$p}_location_id")
            ? ['location_id' => (int) $get("{$p}_location_id"), 'address' => $get("{$p}_address")]
            : (is_numeric($get("{$p}_lat")) && is_numeric($get("{$p}_lng"))
                ? ['lat' => (float) $get("{$p}_lat"), 'lng' => (float) $get("{$p}_lng"), 'address' => $get("{$p}_address") ?: null]
                : null);

        $pickup = $point('pickup');
        $dropoff = $point('dropoff');

        if (! $pickup || ! $dropoff || (! $get('pickup_at') && ! $get('flight_time'))) {
            return null;
        }

        return [
            'pickup' => $pickup,
            'dropoff' => $dropoff,
            'pickup_at' => $get('pickup_at'),
            'flight_number' => $get('flight_number'),
            'flight_time' => $get('flight_time'),
            'return_pickup_at' => $get('round_trip') ? $get('return_pickup_at') : null,
            'return_flight_number' => $get('round_trip') ? $get('return_flight_number') : null,
            'return_flight_time' => $get('round_trip') ? $get('return_flight_time') : null,
            'adults' => max(1, (int) $get('adults')),
            'children' => (int) $get('children'),
            'infants' => (int) $get('infants'),
            'luggage' => (int) $get('luggage'),
            'extras' => collect($get('extras') ?? [])
                ->filter(fn ($e) => ! empty($e['extra_id']))
                ->map(fn ($e) => ['id' => (int) $e['extra_id'], 'quantity' => (int) ($e['quantity'] ?: 1)])
                ->values()->all(),
        ];
    }

    public static function quote(Get $get): ?Quote
    {
        $payload = self::payload($get);
        if (! $payload) {
            return null;
        }

        $agencyId = $get('agency_id');
        $key = md5(serialize([$payload, $agencyId]));

        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        try {
            $agency = $agencyId ? Agency::find($agencyId) : null;
            $request = app(ReservationService::class)->buildQuoteRequest($payload, $agency);

            return self::$memo[$key] = app(PriceCalculator::class)->quote($request);
        } catch (Throwable $e) {
            report($e);

            return self::$memo[$key] = null;
        }
    }

    public static function money(float|string|null $amount, ?string $currency = null): string
    {
        $currency ??= config('transfer.currency');
        $symbol = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'TRY' => '₺'][$currency] ?? $currency.' ';

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}
