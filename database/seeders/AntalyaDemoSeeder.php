<?php

namespace Database\Seeders;

use App\Enums\AdjustmentType;
use App\Enums\BookingSource;
use App\Enums\LocationType;
use App\Enums\ZoneType;
use App\Models\Agency;
use App\Models\Driver;
use App\Models\Extra;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\Route;
use App\Models\Transfer;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Reservations\ReservationService;
use App\Services\Reservations\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Antalya bölgesi için örnek veri. Poligonlar yaklaşık sınırlardır;
 * panelde harita üzerinden düzenlenebilir.
 */
class AntalyaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $zones = $this->zones();
        $types = $this->vehicleTypes();
        $this->routes($zones, $types);
        $this->locations();
        $this->rulesAndExtras();
        $this->fleet($types);

        $agency = Agency::updateOrCreate(['email' => 'ops@sunnytours.example'], [
            'name' => 'Sunny Tours GmbH',
            'contact_name' => 'Anna Weber',
            'phone' => '+49 30 1234567',
            'discount_percent' => 10,
        ]);

        $this->command?->info('Demo acente API token: '.$agency->createToken('demo')->plainTextToken);
        $this->command?->info('Demo sürücü API token: '.Driver::first()->createToken('demo')->plainTextToken);

        $this->sampleReservations($agency);
    }

    private function box(float $latMin, float $lngMin, float $latMax, float $lngMax): array
    {
        return [[$latMin, $lngMin], [$latMin, $lngMax], [$latMax, $lngMax], [$latMax, $lngMin]];
    }

    private function zones(): array
    {
        $data = [
            'AYT' => ['Antalya Havalimanı (AYT)', ZoneType::Airport, '#0ea5e9', $this->box(36.882, 30.775, 36.918, 30.830), 100],
            'GZP' => ['Gazipaşa Havalimanı (GZP)', ZoneType::Airport, '#0284c7', $this->box(36.285, 32.280, 36.315, 32.320), 100],
            'ANT' => ['Antalya Merkez / Konyaaltı', ZoneType::City, '#64748b', $this->box(36.840, 30.560, 36.960, 30.775), 10],
            'LARA' => ['Lara / Kundu', ZoneType::Resort, '#22c55e', $this->box(36.825, 30.775, 36.882, 31.000), 20],
            'BELEK' => ['Belek', ZoneType::Resort, '#16a34a', $this->box(36.800, 31.000, 36.900, 31.180), 20],
            'SIDE' => ['Side / Manavgat', ZoneType::Resort, '#f59e0b', $this->box(36.730, 31.300, 36.830, 31.560), 20],
            'ALANYA' => ['Alanya', ZoneType::City, '#ef4444', [[36.640, 31.700], [36.480, 31.950], [36.520, 32.130], [36.620, 32.100], [36.700, 31.800]], 20],
            'KEMER' => ['Kemer', ZoneType::Resort, '#a855f7', $this->box(36.520, 30.490, 36.700, 30.600), 20],
        ];

        $zones = [];
        foreach ($data as $code => [$name, $type, $color, $polygon, $priority]) {
            $zones[$code] = Zone::updateOrCreate(['code' => $code], compact('name', 'type', 'color', 'polygon', 'priority'));
        }

        return $zones;
    }

    private function vehicleTypes(): array
    {
        $data = [
            // slug => [ad, açıklama, yolcu, bagaj, açılış, km, min]
            'sedan' => ['Ekonomi Sedan', 'Skoda Superb veya benzeri', 3, 3, 15, 1.10, 25],
            'minivan' => ['Minivan', 'VW Caravelle / Mercedes Vito', 7, 7, 20, 1.40, 35],
            'vip' => ['VIP Vito', 'Mercedes Vito VIP, deri koltuk, TV, ikram', 6, 6, 30, 1.80, 50],
            'sprinter' => ['Sprinter', 'Mercedes Sprinter', 14, 14, 35, 2.10, 60],
            'midibus' => ['Midibüs', 'Otokar / Isuzu 27 koltuk', 27, 27, 50, 3.00, 90],
        ];

        $types = [];
        $order = 0;
        foreach ($data as $slug => [$name, $description, $pax, $lug, $base, $km, $min]) {
            $types[$slug] = VehicleType::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'max_passengers' => $pax, 'max_luggage' => $lug,
                'base_fare' => $base, 'per_km_rate' => $km, 'min_fare' => $min, 'sort_order' => $order++,
            ]);
        }

        return $types;
    }

    private function routes(array $z, array $t): void
    {
        // from, to, km, dk, [sedan, minivan, vip, sprinter, midibus]
        $matrix = [
            ['AYT', 'LARA', 12, 20, [25, 35, 45, 60, 90]],
            ['AYT', 'ANT', 15, 25, [28, 38, 50, 65, 100]],
            ['AYT', 'BELEK', 30, 35, [35, 45, 60, 75, 115]],
            ['AYT', 'KEMER', 60, 60, [55, 65, 85, 105, 150]],
            ['AYT', 'SIDE', 65, 60, [55, 65, 85, 110, 160]],
            ['AYT', 'ALANYA', 125, 120, [85, 100, 130, 155, 220]],
            ['GZP', 'ALANYA', 40, 40, [40, 50, 70, 90, 130]],
            ['GZP', 'SIDE', 110, 100, [80, 95, 120, 145, 200]],
            ['ANT', 'KEMER', 45, 50, [45, 55, 75, 95, 140]],
            ['BELEK', 'SIDE', 40, 40, [40, 50, 65, 85, 120]],
            ['LARA', 'ANT', 12, 25, [20, 28, 40, 55, 85]],
        ];

        foreach ($matrix as [$from, $to, $km, $min, $prices]) {
            $route = Route::updateOrCreate(
                ['from_zone_id' => $z[$from]->id, 'to_zone_id' => $z[$to]->id],
                ['distance_km' => $km, 'duration_min' => $min, 'is_bidirectional' => true],
            );

            foreach (array_values($t) as $i => $vt) {
                $route->prices()->updateOrCreate(['vehicle_type_id' => $vt->id], ['price' => $prices[$i]]);
            }
        }
    }

    private function locations(): void
    {
        $data = [
            ['AYT Dış Hatlar Terminal 1', LocationType::Airport, 'AYT', 36.9003, 30.7926],
            ['AYT Dış Hatlar Terminal 2', LocationType::Airport, 'AYT', 36.9058, 30.8025],
            ['AYT İç Hatlar Terminali', LocationType::Airport, 'AYT', 36.8984, 30.8000],
            ['Gazipaşa-Alanya Havalimanı', LocationType::Airport, 'GZP', 36.2993, 32.3006],
            ['Kaleiçi', LocationType::Landmark, null, 36.8841, 30.7056],
            ['Konyaaltı Plajı', LocationType::Landmark, null, 36.8650, 30.6380],
            ['Lara Plajı', LocationType::Landmark, null, 36.8500, 30.8700],
            ['Kundu Otel Bölgesi', LocationType::Hotel, null, 36.8530, 30.9300],
            ['Belek Merkez', LocationType::Landmark, null, 36.8620, 31.0550],
            ['Side Antik Kenti', LocationType::Landmark, null, 36.7675, 31.3890],
            ['Manavgat Şelalesi', LocationType::Landmark, null, 36.8100, 31.4500],
            ['Kleopatra Plajı (Alanya)', LocationType::Landmark, null, 36.5480, 31.9830],
            ['Alanya Limanı', LocationType::Port, null, 36.5370, 32.0010],
            ['Kemer Marina', LocationType::Port, null, 36.6000, 30.5650],
        ];

        foreach ($data as [$name, $type, $iata, $lat, $lng]) {
            Location::updateOrCreate(['name' => $name], [
                'type' => $type, 'iata_code' => $iata, 'lat' => $lat, 'lng' => $lng, 'address' => $name,
                'zone_id' => null, // koordinattan otomatik bulunur
            ]);
        }
    }

    private function rulesAndExtras(): void
    {
        PricingRule::updateOrCreate(['name' => 'Gece Tarifesi'], [
            'adjustment_type' => AdjustmentType::Percent, 'value' => 20, 'time_from' => '00:00', 'time_to' => '06:00', 'priority' => 10,
        ]);
        PricingRule::updateOrCreate(['name' => 'Yüksek Sezon 2027'], [
            'adjustment_type' => AdjustmentType::Percent, 'value' => 10, 'starts_on' => '2027-06-15', 'ends_on' => '2027-09-15', 'priority' => 5,
        ]);
        PricingRule::updateOrCreate(['name' => 'Kış Kampanyası'], [
            'adjustment_type' => AdjustmentType::Percent, 'value' => -10, 'starts_on' => '2026-11-15', 'ends_on' => '2027-02-28',
            'is_active' => false, 'priority' => 1,
        ]);

        foreach ([
            ['BABY_SEAT', 'Bebek Koltuğu', '0-13 kg', 5, true, 3],
            ['CHILD_SEAT', 'Çocuk Koltuğu', '9-18 kg', 5, true, 3],
            ['BOOSTER', 'Yükseltici', '15-36 kg', 3, true, 3],
            ['MEET_GREET', 'İsimli Karşılama', 'Terminal içinde isim tabelası ile karşılama', 10, false, 1],
            ['EXTRA_STOP', 'Ara Durak', 'Market / eczane için 15 dk mola', 15, true, 2],
        ] as [$code, $name, $desc, $price, $perLeg, $max]) {
            Extra::updateOrCreate(['code' => $code], [
                'name' => $name, 'description' => $desc, 'price' => $price, 'per_leg' => $perLeg, 'max_quantity' => $max,
            ]);
        }
    }

    private function fleet(array $types): void
    {
        $drivers = [
            ['Mehmet Yılmaz', '+90 532 111 22 33', ['tr', 'en'], '07 ABC 123', 'sedan', 'Skoda Superb'],
            ['Ahmet Demir', '+90 533 222 33 44', ['tr', 'de'], '07 TRF 07', 'minivan', 'VW Caravelle'],
            ['Hasan Kaya', '+90 535 333 44 55', ['tr', 'en', 'ru'], '07 VIP 34', 'vip', 'Mercedes Vito VIP'],
            ['Ali Çelik', '+90 536 444 55 66', ['tr'], '07 SPR 01', 'sprinter', 'Mercedes Sprinter'],
            ['Mustafa Şahin', '+90 537 555 66 77', ['tr', 'en'], '07 MNV 22', 'minivan', 'Mercedes Vito'],
        ];

        foreach ($drivers as [$name, $phone, $langs, $plate, $type, $model]) {
            $driver = Driver::updateOrCreate(['phone' => $phone], ['name' => $name, 'languages' => $langs]);
            Vehicle::updateOrCreate(['plate' => $plate], [
                'vehicle_type_id' => $types[$type]->id, 'driver_id' => $driver->id, 'brand_model' => $model,
                'year' => 2023, 'color' => 'Siyah',
            ]);
        }
    }

    private function sampleReservations(Agency $agency): void
    {
        Mail::fake(); // seed sırasında e-posta gönderme

        $service = app(ReservationService::class);
        $terminal = Location::where('name', 'AYT Dış Hatlar Terminal 2')->first();
        $names = ['John Smith', 'Anna Müller', 'Ivan Petrov', 'Emma Johnson', 'Lukas Schneider', 'Olga Ivanova', 'Ayşe Korkmaz', 'Peter Jansen'];
        $destinations = Location::where('type', '!=', LocationType::Airport)->get();
        $vehicleTypes = VehicleType::orderBy('sort_order')->get();

        foreach (range(0, 15) as $i) {
            $destination = $destinations[$i % $destinations->count()];
            $pickupAt = now()->addDays(intdiv($i, 4))->setTime(8 + ($i * 3) % 14, [0, 15, 30, 45][$i % 4]);
            $adults = 1 + $i % 4;
            $vt = $vehicleTypes->first(fn ($vt) => $vt->max_passengers >= $adults + 1 && $vt->max_luggage >= $adults);
            $roundTrip = $i % 3 === 0;

            try {
                $service->create([
                    'pickup' => ['location_id' => $terminal->id],
                    'dropoff' => ['location_id' => $destination->id],
                    'pickup_at' => $pickupAt->toDateTimeString(),
                    'flight_number' => ['TK2412', 'PC1234', 'LH1220', 'SU2134', 'BA672'][$i % 5],
                    'flight_time' => $pickupAt->toDateTimeString(),
                    'return_pickup_at' => $roundTrip ? $pickupAt->copy()->addDays(7)->setTime(6, 30)->toDateTimeString() : null,
                    'vehicle_type_id' => $vt->id,
                    'adults' => $adults,
                    'children' => $i % 2,
                    'luggage' => $adults,
                    'passenger_name' => $names[$i % count($names)],
                    'passenger_phone' => '+44 7700 9000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'passenger_email' => 'guest'.$i.'@example.com',
                    'extras' => $i % 4 === 0 ? [['id' => Extra::where('code', 'BABY_SEAT')->value('id'), 'quantity' => 1]] : [],
                ], $i % 2 ? BookingSource::Agency : BookingSource::Website, $i % 2 ? $agency : null);
            } catch (\Throwable $e) {
                $this->command?->warn("Örnek rezervasyon atlandı: {$e->getMessage()}");
            }
        }

        // Bugünkü ilk iki transfere sürücü ata
        $transfers = app(TransferService::class);
        Transfer::query()->whereDate('pickup_at', today())->orderBy('pickup_at')->take(2)->get()
            ->each(function ($transfer) use ($transfers) {
                $vehicle = Vehicle::with('driver', 'vehicleType')
                    ->whereHas('vehicleType', fn ($q) => $q->where('max_passengers', '>=', $transfer->reservation->total_passengers))
                    ->first();
                if ($vehicle?->driver) {
                    $transfers->assign($transfer, $vehicle->driver, $vehicle);
                }
            });
    }
}
