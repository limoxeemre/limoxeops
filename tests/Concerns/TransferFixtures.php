<?php

namespace Tests\Concerns;

use App\Enums\AdjustmentType;
use App\Enums\LocationType;
use App\Enums\ZoneType;
use App\Models\Agency;
use App\Models\Driver;
use App\Models\Extra;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\Route;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Zone;

/**
 * Küçük, deterministik bir dünya:
 *   AIRPORT (yüksek öncelik) şehir poligonunun içinde, RESORT ayrı.
 *   AIRPORT ⇄ RESORT sabit rota: sedan 30, van 50. Gece %20 zam.
 */
trait TransferFixtures
{
    protected Zone $airport;

    protected Zone $city;

    protected Zone $resort;

    protected VehicleType $sedan;

    protected VehicleType $van;

    protected Location $terminal;

    protected Location $hotel;

    protected Extra $babySeat;

    protected function seedWorld(): void
    {
        $this->city = Zone::create(['name' => 'City', 'code' => 'CITY', 'type' => ZoneType::City, 'priority' => 10,
            'polygon' => [[36.80, 30.60], [36.80, 30.90], [37.00, 30.90], [37.00, 30.60]]]);
        $this->airport = Zone::create(['name' => 'Airport', 'code' => 'APT', 'type' => ZoneType::Airport, 'priority' => 100,
            'polygon' => [[36.88, 30.78], [36.88, 30.83], [36.92, 30.83], [36.92, 30.78]]]);
        $this->resort = Zone::create(['name' => 'Resort', 'code' => 'RES', 'type' => ZoneType::Resort, 'priority' => 20,
            'polygon' => [[36.80, 31.00], [36.80, 31.20], [36.90, 31.20], [36.90, 31.00]]]);

        $this->sedan = VehicleType::create(['name' => 'Sedan', 'slug' => 'sedan', 'max_passengers' => 3, 'max_luggage' => 3,
            'base_fare' => 10, 'per_km_rate' => 1, 'min_fare' => 20, 'sort_order' => 1]);
        $this->van = VehicleType::create(['name' => 'Van', 'slug' => 'van', 'max_passengers' => 7, 'max_luggage' => 7,
            'base_fare' => 0, 'per_km_rate' => 0, 'sort_order' => 2]); // km tarifesi yok

        $route = Route::create(['from_zone_id' => $this->airport->id, 'to_zone_id' => $this->resort->id,
            'distance_km' => 30, 'duration_min' => 35, 'is_bidirectional' => true]);
        $route->prices()->createMany([
            ['vehicle_type_id' => $this->sedan->id, 'price' => 30],
            ['vehicle_type_id' => $this->van->id, 'price' => 50],
        ]);

        PricingRule::create(['name' => 'Gece', 'adjustment_type' => AdjustmentType::Percent, 'value' => 20,
            'time_from' => '00:00', 'time_to' => '06:00']);

        $this->terminal = Location::create(['name' => 'Terminal', 'type' => LocationType::Airport, 'lat' => 36.90, 'lng' => 30.80]);
        $this->hotel = Location::create(['name' => 'Hotel', 'type' => LocationType::Hotel, 'lat' => 36.85, 'lng' => 31.05]);

        $this->babySeat = Extra::create(['name' => 'Bebek koltuğu', 'code' => 'BABY', 'price' => 5, 'per_leg' => true, 'max_quantity' => 2]);
    }

    protected function makeDriver(?VehicleType $type = null): Driver
    {
        $driver = Driver::create(['name' => 'Ali', 'phone' => '+90 555 000 00 00']);
        Vehicle::create(['vehicle_type_id' => ($type ?? $this->van)->id, 'driver_id' => $driver->id, 'plate' => '07 T '.random_int(100, 999)]);

        return $driver;
    }

    protected function makeAgency(float $discount = 10): Agency
    {
        return Agency::create(['name' => 'Partner', 'email' => 'p'.random_int(1, 99999).'@example.com', 'discount_percent' => $discount]);
    }

    protected function reservationPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'pickup' => ['location_id' => $this->terminal->id],
            'dropoff' => ['location_id' => $this->hotel->id],
            'pickup_at' => now()->addDays(3)->setTime(14, 0)->toDateTimeString(),
            'flight_number' => 'TK 2412',
            'adults' => 2,
            'luggage' => 2,
            'vehicle_type_id' => $this->sedan->id,
            'passenger_name' => 'John Smith',
            'passenger_phone' => '+44 7700 900123',
            'passenger_email' => 'john@example.com',
        ], $overrides);
    }
}
