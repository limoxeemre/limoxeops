<?php

namespace App\Http\Resources;

use App\Models\Driver;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transfer */
class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $forDriver = $request->user('sanctum') instanceof Driver;

        return [
            'id' => $this->id,
            'reservation_code' => $this->reservation?->code,
            'direction' => $this->direction->value,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'pickup_at' => $this->pickup_at->toIso8601String(),
            'estimated_end_at' => $this->estimatedEndAt()->toIso8601String(),
            'flight_number' => $this->flight_number,
            'flight_time' => $this->flight_time?->toIso8601String(),
            'pickup' => [
                'address' => $this->pickup_address,
                'lat' => $this->pickup_lat,
                'lng' => $this->pickup_lng,
                'zone' => $this->pickupZone?->name,
            ],
            'dropoff' => [
                'address' => $this->dropoff_address,
                'lat' => $this->dropoff_lat,
                'lng' => $this->dropoff_lng,
                'zone' => $this->dropoffZone?->name,
            ],
            'distance_km' => $this->distance_km,
            'duration_min' => $this->duration_min,
            'price' => $this->when(! $forDriver, (float) $this->price),
            'navigation_url' => $this->google_maps_url,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
            ] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'plate' => $this->vehicle->plate,
                'model' => $this->vehicle->brand_model,
                'color' => $this->vehicle->color,
            ] : null),
            // Sürücü uygulaması için yolcu bilgisi
            'passenger' => $this->when($forDriver, fn () => [
                'name' => $this->reservation->passenger_name,
                'phone' => $this->reservation->passenger_phone,
                'adults' => $this->reservation->adults,
                'children' => $this->reservation->children,
                'infants' => $this->reservation->infants,
                'luggage' => $this->reservation->luggage,
                'notes' => $this->reservation->customer_notes,
                'extras' => $this->reservation->extras->map(fn ($e) => $e->extra->name.' × '.$e->quantity),
                'collect_payment' => $this->reservation->payment_status->value === 'unpaid'
                    && in_array($this->reservation->payment_method?->value, ['cash', 'card'], true)
                    ? ['amount' => (float) $this->reservation->total_price, 'currency' => $this->reservation->currency]
                    : null,
            ]),
            'driver_notes' => $this->when($forDriver, $this->driver_notes),
        ];
    }
}
