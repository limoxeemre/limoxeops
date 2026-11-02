<?php

namespace Tests\Feature;

use App\Enums\PriceSource;
use App\Models\PricingRule;
use App\Services\Geo\Waypoint;
use App\Services\Geo\ZoneResolver;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorld();
    }

    private function quote(array $pickup, array $dropoff, string $at = '+3 days 14:00', array $extra = [])
    {
        return app(PriceCalculator::class)->quote(new QuoteRequest(...[
            'pickup' => Waypoint::fromArray($pickup),
            'dropoff' => Waypoint::fromArray($dropoff),
            'pickupAt' => CarbonImmutable::parse($at),
            ...$extra,
        ]));
    }

    public function test_higher_priority_zone_wins_when_polygons_overlap(): void
    {
        $resolver = app(ZoneResolver::class);

        $this->assertSame($this->airport->id, $resolver->resolve(36.90, 30.80)->id); // havalimanı, şehrin içinde
        $this->assertSame($this->city->id, $resolver->resolve(36.95, 30.70)->id);
        $this->assertNull($resolver->resolve(40.0, 30.0));
    }

    public function test_location_zone_is_assigned_from_coordinates(): void
    {
        $this->assertSame($this->airport->id, $this->terminal->zone_id);
        $this->assertSame($this->resort->id, $this->hotel->zone_id);
    }

    public function test_fixed_route_price_for_each_vehicle_type(): void
    {
        $quote = $this->quote(['location_id' => $this->terminal->id], ['lat' => 36.86, 'lng' => 31.10, 'address' => 'Some hotel']);

        $this->assertSame(30.0, $quote->forVehicleType($this->sedan->id)->total);
        $this->assertSame(50.0, $quote->forVehicleType($this->van->id)->total);
        $this->assertSame(PriceSource::FixedRoute, $quote->forVehicleType($this->sedan->id)->legs[0]->source);
        $this->assertSame(35, $quote->durationMin);
    }

    public function test_bidirectional_route_applies_in_reverse(): void
    {
        $quote = $this->quote(['location_id' => $this->hotel->id], ['location_id' => $this->terminal->id]);

        $this->assertSame(30.0, $quote->forVehicleType($this->sedan->id)->total);
    }

    public function test_night_surcharge_is_applied_only_inside_window(): void
    {
        $night = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], '+3 days 02:30');
        $edge = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], '+3 days 06:00');

        $leg = $night->forVehicleType($this->sedan->id)->legs[0];
        $this->assertSame(30.0, $leg->basePrice);
        $this->assertSame('Gece', $leg->adjustments[0]['name']);
        $this->assertSame(36.0, $leg->price);
        $this->assertSame(30.0, $edge->forVehicleType($this->sedan->id)->total);
    }

    public function test_overnight_time_window_wraps_midnight(): void
    {
        PricingRule::query()->update(['time_from' => '22:00', 'time_to' => '05:00']);

        $late = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], '+3 days 23:15');
        $early = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], '+3 days 04:59');
        $day = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], '+3 days 12:00');

        $this->assertSame(36.0, $late->forVehicleType($this->sedan->id)->total);
        $this->assertSame(36.0, $early->forVehicleType($this->sedan->id)->total);
        $this->assertSame(30.0, $day->forVehicleType($this->sedan->id)->total);
    }

    public function test_distance_fallback_when_no_route_and_vehicles_without_tariff_are_hidden(): void
    {
        // City → Resort arasında rota yok
        $quote = $this->quote(['lat' => 36.95, 'lng' => 30.70, 'address' => 'Old town'], ['location_id' => $this->hotel->id]);

        $sedan = $quote->forVehicleType($this->sedan->id);
        $this->assertNotNull($sedan);
        $this->assertSame(PriceSource::Distance, $sedan->legs[0]->source);
        $this->assertGreaterThan(20, $sedan->total);
        $this->assertNull($quote->forVehicleType($this->van->id), 'Km tarifesi olmayan araç listelenmemeli');
    }

    public function test_distance_fallback_can_be_disabled(): void
    {
        config(['transfer.pricing.distance_fallback' => false]);

        $quote = $this->quote(['lat' => 36.95, 'lng' => 30.70], ['location_id' => $this->hotel->id]);

        $this->assertTrue($quote->isEmpty());
    }

    public function test_capacity_filters_vehicle_types(): void
    {
        $quote = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id], extra: ['adults' => 4, 'luggage' => 4]);

        $this->assertNull($quote->forVehicleType($this->sedan->id));
        $this->assertNotNull($quote->forVehicleType($this->van->id));
    }

    public function test_round_trip_extras_and_agency_discount(): void
    {
        $quote = $this->quote(
            ['location_id' => $this->terminal->id],
            ['location_id' => $this->hotel->id],
            '+3 days 14:00',
            [
                'returnAt' => CarbonImmutable::parse('+10 days 03:00'), // gece dönüş
                'extras' => [$this->babySeat->id => 1],
                'agency' => $this->makeAgency(10),
            ],
        );

        $option = $quote->forVehicleType($this->sedan->id);
        // gidiş 30 + dönüş 36 (gece) + bebek koltuğu 5×2 yön = 76, %10 indirim
        $this->assertCount(2, $option->legs);
        $this->assertSame(36.0, $option->legs[1]->price);
        $this->assertSame(10.0, $option->extras[0]['total']);
        $this->assertSame(76.0, $option->subtotal);
        $this->assertSame(7.6, $option->discount);
        $this->assertSame(68.4, $option->total);
    }

    public function test_seasonal_rule_limited_to_vehicle_type_and_zone(): void
    {
        PricingRule::create([
            'name' => 'Resort van promo', 'adjustment_type' => 'fixed', 'value' => -5,
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addMonth()->toDateString(),
            'vehicle_type_id' => $this->van->id, 'zone_id' => $this->resort->id,
        ]);

        $quote = $this->quote(['location_id' => $this->terminal->id], ['location_id' => $this->hotel->id]);

        $this->assertSame(30.0, $quote->forVehicleType($this->sedan->id)->total);
        $this->assertSame(45.0, $quote->forVehicleType($this->van->id)->total);
    }
}
