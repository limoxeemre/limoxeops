<?php

namespace App\Services\Reservations;

use App\Services\Geo\Waypoint;
use Carbon\CarbonInterface;

/**
 * Uçuş saatinden araç alış saatini önerir.
 *  - Havalimanından alış (geliş): iniş saati + karşılama gecikmesi
 *  - Havalimanına bırakış (gidiş): kalkış − yol süresi − havalimanı tamponu (varsayılan 150 dk)
 */
class PickupTimeSuggester
{
    public function suggest(Waypoint $pickup, Waypoint $dropoff, CarbonInterface $flightTime, int $durationMin): CarbonInterface
    {
        if ($pickup->isAirport()) {
            return $flightTime->copy()->addMinutes((int) config('transfer.operations.arrival_pickup_delay_min'));
        }

        if ($dropoff->isAirport()) {
            $minutes = $durationMin + (int) config('transfer.operations.departure_airport_buffer_min');

            // 5 dakikalık dilime aşağı yuvarla (09:43 → 09:40) – sürücü ve yolcu için okunaklı
            $time = $flightTime->copy()->subMinutes($minutes);

            return $time->subMinutes($time->minute % 5)->second(0);
        }

        return $flightTime->copy();
    }
}
