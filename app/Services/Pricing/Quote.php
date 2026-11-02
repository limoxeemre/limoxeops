<?php

namespace App\Services\Pricing;

use Illuminate\Support\Collection;

final class Quote
{
    /**
     * @param  Collection<int, VehicleQuote>  $options
     */
    public function __construct(
        public readonly QuoteRequest $request,
        public readonly Collection $options,
        public readonly float $distanceKm,
        public readonly int $durationMin,
    ) {}

    public function forVehicleType(int $vehicleTypeId): ?VehicleQuote
    {
        return $this->options->first(fn (VehicleQuote $o) => $o->vehicleType->id === $vehicleTypeId);
    }

    public function isEmpty(): bool
    {
        return $this->options->isEmpty();
    }

    public function toArray(): array
    {
        return [
            'pickup' => $this->request->pickup->toArray(),
            'dropoff' => $this->request->dropoff->toArray(),
            'pickup_at' => $this->request->pickupAt->toIso8601String(),
            'return_at' => $this->request->returnAt?->toIso8601String(),
            'passengers' => $this->request->passengers(),
            'luggage' => $this->request->luggage,
            'distance_km' => round($this->distanceKm, 1),
            'duration_min' => $this->durationMin,
            'options' => $this->options->map->toArray()->values()->all(),
        ];
    }
}
