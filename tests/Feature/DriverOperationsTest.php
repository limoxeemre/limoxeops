<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\ReservationStatus;
use App\Enums\TransferStatus;
use App\Models\Reservation;
use App\Services\Reservations\ReservationService;
use App\Services\Reservations\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class DriverOperationsTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedWorld();
        Mail::fake();
        $this->reservation = app(ReservationService::class)->create($this->reservationPayload(), BookingSource::Admin);
    }

    public function test_full_driver_flow_via_api(): void
    {
        $driver = $this->makeDriver();
        $transfer = $this->reservation->transfers->first();
        app(TransferService::class)->assign($transfer, $driver);
        $token = $driver->createToken('app')->plainTextToken;

        $date = $transfer->pickup_at->toDateString();
        $this->withToken($token)->getJson("/api/v1/driver/transfers?date={$date}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.passenger.name', 'John Smith')
            ->assertJsonPath('data.0.passenger.collect_payment.amount', 30)
            ->assertJsonMissingPath('data.0.price');

        foreach (['en_route', 'on_board', 'completed'] as $status) {
            $this->withToken($token)->patchJson("/api/v1/driver/transfers/{$transfer->id}/status", [
                'status' => $status, 'lat' => 36.9, 'lng' => 30.8,
            ])->assertOk()->assertJsonPath('data.status', $status);
        }

        $this->assertSame(ReservationStatus::Completed, $this->reservation->refresh()->status);
        $this->assertNotNull($transfer->refresh()->completed_at);
        $this->assertSame(5, $transfer->statusLogs()->count()); // oluşturma + atama + 3 durum
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $driver = $this->makeDriver();
        $transfer = $this->reservation->transfers->first();
        app(TransferService::class)->assign($transfer, $driver);

        $this->withToken($driver->createToken('app')->plainTextToken)
            ->patchJson("/api/v1/driver/transfers/{$transfer->id}/status", ['status' => 'completed'])
            ->assertUnprocessable();
    }

    public function test_driver_cannot_see_other_drivers_transfer(): void
    {
        $transfer = $this->reservation->transfers->first();
        app(TransferService::class)->assign($transfer, $this->makeDriver());
        $intruder = $this->makeDriver();

        $this->withToken($intruder->createToken('app')->plainTextToken)
            ->getJson("/api/v1/driver/transfers/{$transfer->id}")
            ->assertNotFound();
    }

    public function test_conflicting_assignments_are_detected(): void
    {
        $driver = $this->makeDriver();
        $first = $this->reservation->transfers->first();
        app(TransferService::class)->assign($first, $driver);

        // 20 dk sonra başka bir iş: ilk transfer 35 dk + 30 dk tampon → çakışır
        $second = app(ReservationService::class)->create($this->reservationPayload([
            'pickup_at' => $first->pickup_at->copy()->addMinutes(20)->toDateTimeString(),
        ]), BookingSource::Admin)->transfers->first();
        // 3 saat sonra: çakışmaz
        $third = app(ReservationService::class)->create($this->reservationPayload([
            'pickup_at' => $first->pickup_at->copy()->addHours(3)->toDateTimeString(),
        ]), BookingSource::Admin)->transfers->first();

        $service = app(TransferService::class);
        $this->assertCount(1, $service->conflictsFor($second, $driver));
        $this->assertCount(0, $service->conflictsFor($third, $driver));
    }

    public function test_vehicle_capacity_is_enforced_on_assignment(): void
    {
        $reservation = app(ReservationService::class)->create($this->reservationPayload([
            'adults' => 5, 'luggage' => 3, 'vehicle_type_id' => $this->van->id,
        ]), BookingSource::Admin);

        $this->expectException(ValidationException::class);
        app(TransferService::class)->assign($reservation->transfers->first(), $this->makeDriver($this->sedan));
    }

    public function test_cancelling_reservation_cancels_all_legs(): void
    {
        $res = app(ReservationService::class)->create($this->reservationPayload([
            'return_pickup_at' => now()->addDays(8)->toDateTimeString(),
        ]), BookingSource::Admin);
        app(TransferService::class)->assign($res->transfers->first(), $this->makeDriver());

        app(ReservationService::class)->cancel($res, 'Uçuş iptal');

        $this->assertSame(ReservationStatus::Cancelled, $res->status);
        $this->assertTrue($res->transfers()->get()->every(fn ($t) => $t->status === TransferStatus::Cancelled));
    }

    public function test_manual_price_for_route_without_tariff(): void
    {
        $res = app(ReservationService::class)->create($this->reservationPayload([
            'pickup' => ['lat' => 41.0, 'lng' => 29.0, 'address' => 'İstanbul'],
            'vehicle_type_id' => $this->van->id,
        ]), BookingSource::Admin, manualPrice: 400);

        $this->assertSame('400.00', $res->total_price);
        $this->assertSame('manual', $res->transfers->first()->price_source->value);
    }
}
