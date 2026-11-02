<?php

namespace App\Services\Reservations;

use App\Enums\BookingSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PriceSource;
use App\Enums\ReservationStatus;
use App\Enums\TransferDirection;
use App\Enums\TransferStatus;
use App\Enums\TripType;
use App\Mail\ReservationConfirmation;
use App\Models\Agency;
use App\Models\Reservation;
use App\Services\Geo\Waypoint;
use App\Services\Pricing\LegQuote;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly PickupTimeSuggester $timeSuggester,
    ) {}

    /**
     * Veriyi QuoteRequest'e çevirir. Alış saati verilmemiş ama uçuş saati verilmişse
     * alış saati otomatik önerilir.
     */
    public function buildQuoteRequest(array $data, ?Agency $agency = null): QuoteRequest
    {
        $pickup = Waypoint::fromArray($data['pickup']);
        $dropoff = Waypoint::fromArray($data['dropoff']);

        $duration = $this->calculator->findRoute($pickup->zone, $dropoff->zone)?->duration_min
            ?? $this->calculator->estimateDuration($this->calculator->roadDistance($pickup, $dropoff));

        $pickupAt = $this->resolveTime($data['pickup_at'] ?? null, $data['flight_time'] ?? null, $pickup, $dropoff, $duration);

        $returnAt = null;
        if (! empty($data['return_pickup_at']) || ! empty($data['return_flight_time'])) {
            $returnAt = $this->resolveTime($data['return_pickup_at'] ?? null, $data['return_flight_time'] ?? null, $dropoff, $pickup, $duration);

            if ($returnAt->lte($pickupAt)) {
                throw ValidationException::withMessages(['return_pickup_at' => 'Dönüş saati gidiş saatinden sonra olmalıdır.']);
            }
        }

        return new QuoteRequest(
            pickup: $pickup,
            dropoff: $dropoff,
            pickupAt: $pickupAt,
            returnAt: $returnAt,
            adults: (int) ($data['adults'] ?? 1),
            children: (int) ($data['children'] ?? 0),
            infants: (int) ($data['infants'] ?? 0),
            luggage: (int) ($data['luggage'] ?? 0),
            extras: $this->normalizeExtras($data['extras'] ?? []),
            agency: $agency,
            vehicleTypeId: isset($data['vehicle_type_id']) ? (int) $data['vehicle_type_id'] : null,
        );
    }

    /**
     * Rezervasyonu ve transfer bacaklarını oluşturur. Fiyat her zaman sunucuda yeniden hesaplanır;
     * istemciden gelen fiyata güvenilmez (yalnızca panelden manuel fiyat girilebilir).
     */
    public function create(
        array $data,
        BookingSource $source,
        ?Agency $agency = null,
        ?Model $causer = null,
        ?float $manualPrice = null,
    ): Reservation {
        $request = $this->buildQuoteRequest($data, $agency);

        if (! $request->vehicleTypeId) {
            throw ValidationException::withMessages(['vehicle_type_id' => 'Araç tipi seçilmelidir.']);
        }

        $quote = $this->calculator->quote($request);
        $option = $quote->forVehicleType($request->vehicleTypeId);

        if (! $option && $manualPrice === null) {
            throw ValidationException::withMessages([
                'vehicle_type_id' => 'Seçilen araç tipi bu güzergah veya yolcu/bagaj sayısı için uygun değil.',
            ]);
        }

        $reservation = DB::transaction(function () use ($data, $source, $agency, $request, $option, $manualPrice, $causer) {
            $subtotal = $manualPrice ?? $option->subtotal;
            $discount = $manualPrice !== null ? 0 : $option->discount;
            $status = isset($data['status']) ? ReservationStatus::from($data['status']) : ($source === BookingSource::Website
                ? ReservationStatus::Pending
                : ReservationStatus::Confirmed);

            $reservation = Reservation::create([
                'code' => Reservation::generateCode(),
                'agency_id' => $agency?->id,
                'agency_reference' => $data['agency_reference'] ?? null,
                'vehicle_type_id' => $request->vehicleTypeId,
                'status' => $status,
                'source' => $source,
                'trip_type' => $request->isRoundTrip() ? TripType::RoundTrip : TripType::OneWay,
                'passenger_name' => $data['passenger_name'],
                'passenger_phone' => $data['passenger_phone'],
                'passenger_email' => $data['passenger_email'],
                'passenger_country' => $data['passenger_country'] ?? null,
                'adults' => $request->adults,
                'children' => $request->children,
                'infants' => $request->infants,
                'luggage' => $request->luggage,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_price' => round($subtotal - $discount, 2),
                'currency' => config('transfer.currency'),
                'price_breakdown' => $option?->toArray(),
                'payment_status' => $data['payment_status'] ?? PaymentStatus::Unpaid,
                'payment_method' => $data['payment_method'] ?? ($agency ? PaymentMethod::Invoice : PaymentMethod::CashToDriver),
                'customer_notes' => $data['customer_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'confirmed_at' => $status === ReservationStatus::Confirmed ? now() : null,
            ]);

            $legs = $option?->legs ?? $this->manualLegs($request);
            $legCount = count($legs);

            foreach ($legs as $leg) {
                $isOutbound = $leg->direction === TransferDirection::Outbound;
                [$from, $to] = $isOutbound ? [$request->pickup, $request->dropoff] : [$request->dropoff, $request->pickup];
                $prefix = $isOutbound ? '' : 'return_';

                $transfer = $reservation->transfers()->create([
                    'direction' => $leg->direction,
                    'status' => TransferStatus::Pending,
                    'pickup_at' => $leg->pickupAt,
                    'flight_number' => isset($data[$prefix.'flight_number']) ? strtoupper(str_replace(' ', '', $data[$prefix.'flight_number'])) : null,
                    'flight_time' => $data[$prefix.'flight_time'] ?? null,
                    ...$this->waypointColumns('pickup', $from),
                    ...$this->waypointColumns('dropoff', $to),
                    'route_id' => $leg->route?->id,
                    'price_source' => $manualPrice !== null ? PriceSource::Manual : $leg->source,
                    'distance_km' => $leg->distanceKm,
                    'duration_min' => $leg->durationMin,
                    'price' => $manualPrice !== null ? round($manualPrice / $legCount, 2) : $leg->price,
                ]);

                $transfer->statusLogs()->create([
                    'to_status' => TransferStatus::Pending,
                    'causer_type' => $causer?->getMorphClass(),
                    'causer_id' => $causer?->getKey(),
                    'note' => 'Rezervasyon oluşturuldu',
                ]);
            }

            foreach ($option?->extras ?? [] as $line) {
                $reservation->extras()->create([
                    'extra_id' => $line['extra_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'total_price' => $line['total'],
                ]);
            }

            return $reservation;
        });

        Mail::to($reservation->passenger_email)->queue(new ReservationConfirmation($reservation));

        return $reservation->load('transfers', 'extras.extra', 'vehicleType');
    }

    public function confirm(Reservation $reservation): Reservation
    {
        $reservation->update(['status' => ReservationStatus::Confirmed, 'confirmed_at' => now()]);

        return $reservation;
    }

    public function cancel(Reservation $reservation, ?string $reason = null, ?Model $causer = null): Reservation
    {
        DB::transaction(function () use ($reservation, $reason, $causer) {
            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            foreach ($reservation->transfers()->active()->get() as $transfer) {
                app(TransferService::class)->changeStatus($transfer, TransferStatus::Cancelled, $causer, $reason ?: 'Rezervasyon iptal edildi', force: true);
            }
        });

        return $reservation->refresh();
    }

    /** Yolcunun kendisinin iptal edip edemeyeceği (ücretsiz iptal süresi). */
    public function isCancellableByCustomer(Reservation $reservation): bool
    {
        $first = $reservation->transfers()->orderBy('pickup_at')->first();

        return $reservation->status->isOpen()
            && $first?->status === TransferStatus::Pending
            && now()->addHours((int) config('transfer.operations.free_cancellation_hours'))->lt($first->pickup_at);
    }

    private function resolveTime(?string $pickupAt, ?string $flightTime, Waypoint $from, Waypoint $to, int $duration): CarbonImmutable
    {
        if ($pickupAt) {
            return CarbonImmutable::parse($pickupAt)->second(0);
        }

        if ($flightTime) {
            return CarbonImmutable::instance($this->timeSuggester->suggest($from, $to, CarbonImmutable::parse($flightTime), $duration));
        }

        throw ValidationException::withMessages(['pickup_at' => 'Alış saati veya uçuş saati girilmelidir.']);
    }

    /** @return array<int, int> */
    private function normalizeExtras(array $extras): array
    {
        // Hem [{id: 1, quantity: 2}] hem de {1: 2} biçimini kabul et
        if (Arr::isList($extras)) {
            return collect($extras)
                ->filter(fn ($e) => ! empty($e['id'] ?? $e['extra_id'] ?? null))
                ->mapWithKeys(fn ($e) => [(int) ($e['id'] ?? $e['extra_id']) => (int) ($e['quantity'] ?? 1)])
                ->all();
        }

        return array_map('intval', $extras);
    }

    private function waypointColumns(string $prefix, Waypoint $wp): array
    {
        return [
            "{$prefix}_location_id" => $wp->location?->id,
            "{$prefix}_zone_id" => $wp->zone?->id,
            "{$prefix}_address" => $wp->address,
            "{$prefix}_lat" => $wp->lat,
            "{$prefix}_lng" => $wp->lng,
        ];
    }

    /** Fiyat tanımı olmayan güzergahta manuel fiyatla oluşturulan bacaklar. */
    private function manualLegs(QuoteRequest $request): array
    {
        $distance = $this->calculator->roadDistance($request->pickup, $request->dropoff);
        $duration = $this->calculator->estimateDuration($distance);
        $make = fn (TransferDirection $dir, $at) => new LegQuote($dir, $at, PriceSource::Manual, 0, [], 0, $distance, $duration);

        return array_values(array_filter([
            $make(TransferDirection::Outbound, $request->pickupAt),
            $request->returnAt ? $make(TransferDirection::Return, $request->returnAt) : null,
        ]));
    }
}
