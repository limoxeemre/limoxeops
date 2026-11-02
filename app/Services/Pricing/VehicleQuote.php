<?php

namespace App\Services\Pricing;

use App\Models\VehicleType;

final class VehicleQuote
{
    /**
     * @param  list<LegQuote>  $legs
     * @param  list<array{extra_id: int, code: string, name: string, quantity: int, unit_price: float, total: float}>  $extras
     */
    public function __construct(
        public readonly VehicleType $vehicleType,
        public readonly array $legs,
        public readonly array $extras,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $total,
        public readonly string $currency,
    ) {}

    public function toArray(): array
    {
        return [
            'vehicle_type' => [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'slug' => $this->vehicleType->slug,
                'description' => $this->vehicleType->description,
                'max_passengers' => $this->vehicleType->max_passengers,
                'max_luggage' => $this->vehicleType->max_luggage,
            ],
            'legs' => array_map(fn (LegQuote $l) => $l->toArray(), $this->legs),
            'extras' => $this->extras,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'currency' => $this->currency,
        ];
    }
}
