<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Mail\ReservationConfirmation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class ReservationApiTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorld();
        Mail::fake();
    }

    public function test_catalog_endpoints(): void
    {
        $this->getJson('/api/v1/locations?search=term')->assertOk()->assertJsonPath('data.0.name', 'Terminal');
        $this->getJson('/api/v1/vehicle-types')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/extras')->assertOk()->assertJsonPath('data.0.code', 'BABY');
        $this->getJson('/api/v1/geo/zone?lat=36.90&lng=30.80')->assertOk()
            ->assertJsonPath('data.in_service_area', true)->assertJsonPath('data.zone.code', 'APT');
        $this->getJson('/api/v1/geo/zone?lat=40&lng=30')->assertJsonPath('data.in_service_area', false);
    }

    public function test_quote_returns_options_sorted_by_price(): void
    {
        $payload = $this->reservationPayload();
        unset($payload['vehicle_type_id']); // filtre yok → tüm uygun araçlar

        $this->postJson('/api/v1/quotes', $payload)
            ->assertOk()
            ->assertJsonPath('meta.available', true)
            ->assertJsonPath('data.pickup.zone_name', 'Airport')
            ->assertJsonPath('data.dropoff.zone_name', 'Resort')
            ->assertJsonPath('data.options.0.vehicle_type.slug', 'sedan')
            ->assertJsonPath('data.options.0.total', 30)
            ->assertJsonPath('data.options.1.total', 50);
    }

    public function test_quote_validation(): void
    {
        $this->postJson('/api/v1/quotes', ['pickup' => [], 'adults' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup', 'dropoff', 'adults', 'pickup_at']);

        // minimum ön süre (6 saat) kontrolü
        $this->postJson('/api/v1/quotes', $this->reservationPayload(['pickup_at' => now()->addHour()->toDateTimeString()]))
            ->assertJsonValidationErrors(['pickup_at']);
    }

    public function test_pickup_time_is_suggested_from_departure_flight(): void
    {
        $flight = now()->addDays(5)->setTime(18, 0);

        $response = $this->postJson('/api/v1/quotes', $this->reservationPayload([
            'pickup' => ['location_id' => $this->hotel->id],
            'dropoff' => ['location_id' => $this->terminal->id],
            'pickup_at' => null,
            'flight_time' => $flight->toDateTimeString(),
        ]))->assertOk();

        // 18:00 − 35 dk yol − 150 dk tampon = 14:55
        $this->assertSame($flight->copy()->setTime(14, 55)->toIso8601String(), $response->json('data.pickup_at'));
    }

    public function test_public_reservation_is_created_with_server_side_price(): void
    {
        $response = $this->postJson('/api/v1/reservations', $this->reservationPayload([
            'return_pickup_at' => now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            'extras' => [['id' => $this->babySeat->id, 'quantity' => 1]],
            'total_price' => 1, // istemci fiyatı yok sayılmalı
        ]))->assertCreated();

        $response->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.trip_type', 'round_trip')
            ->assertJsonPath('data.price.total', 70) // 30 + 30 + 2×5
            ->assertJsonCount(2, 'data.transfers')
            ->assertJsonPath('data.transfers.0.flight_number', 'TK2412');

        $reservation = Reservation::where('code', $response->json('data.code'))->firstOrFail();
        $this->assertSame(BookingSource::Website, $reservation->source);
        $this->assertSame(1, $reservation->transfers()->first()->statusLogs()->count());
        Mail::assertQueued(ReservationConfirmation::class, fn ($m) => $m->hasTo('john@example.com'));
    }

    public function test_reservation_rejected_when_vehicle_too_small(): void
    {
        $this->postJson('/api/v1/reservations', $this->reservationPayload(['adults' => 5, 'luggage' => 2]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_type_id']);
    }

    public function test_lookup_requires_matching_email(): void
    {
        $code = $this->postJson('/api/v1/reservations', $this->reservationPayload())->json('data.code');

        $this->getJson("/api/v1/reservations/{$code}")->assertJsonValidationErrors(['email']);
        $this->getJson("/api/v1/reservations/{$code}?email=wrong@example.com")->assertNotFound();
        $this->getJson("/api/v1/reservations/{$code}?email=john@example.com")->assertOk()->assertJsonPath('data.code', $code);
    }

    public function test_customer_can_cancel_before_free_cancellation_deadline(): void
    {
        $code = $this->postJson('/api/v1/reservations', $this->reservationPayload())->json('data.code');

        $this->postJson("/api/v1/reservations/{$code}/cancel", ['email' => 'john@example.com'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.transfers.0.status', 'cancelled');
    }

    public function test_customer_cannot_cancel_inside_deadline(): void
    {
        $code = $this->postJson('/api/v1/reservations', $this->reservationPayload([
            'pickup_at' => now()->addHours(10)->toDateTimeString(),
        ]))->json('data.code');

        $this->postJson("/api/v1/reservations/{$code}/cancel", ['email' => 'john@example.com'])->assertUnprocessable();
    }

    public function test_agency_token_gets_discount_and_scoped_access(): void
    {
        $agency = $this->makeAgency(10);
        $token = $agency->createToken('test')->plainTextToken;
        $other = $this->makeAgency(0)->createToken('x')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/reservations', $this->reservationPayload(['agency_reference' => 'SUN-42']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.price.discount', 3)
            ->assertJsonPath('data.price.total', 27)
            ->assertJsonPath('data.agency_reference', 'SUN-42');

        $code = $response->json('data.code');
        $this->withToken($token)->getJson("/api/v1/reservations/{$code}")->assertOk(); // e-posta gerekmez
        $this->withToken($token)->getJson('/api/v1/agency/reservations')->assertOk()->assertJsonCount(1, 'data');

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson("/api/v1/reservations/{$code}")->assertNotFound();
    }

    public function test_agency_endpoint_rejects_driver_token(): void
    {
        $token = $this->makeDriver()->createToken('d')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/agency/reservations')->assertForbidden();
    }
}
