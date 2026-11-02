<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Enums\TransferDirection;
use App\Models\Route;
use Carbon\CarbonInterface;

final class LegQuote
{
    /**
     * @param  list<array{rule_id: int, name: string, amount: float}>  $adjustments
     */
    public function __construct(
        public readonly TransferDirection $direction,
        public readonly CarbonInterface $pickupAt,
        public readonly PriceSource $source,
        public readonly float $basePrice,
        public readonly array $adjustments,
        public readonly float $price,
        public readonly float $distanceKm,
        public readonly int $durationMin,
        public readonly ?Route $route = null,
    ) {}

    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'pickup_at' => $this->pickupAt->toIso8601String(),
            'price_source' => $this->source->value,
            'route_id' => $this->route?->id,
            'base_price' => $this->basePrice,
            'adjustments' => $this->adjustments,
            'price' => $this->price,
            'distance_km' => round($this->distanceKm, 1),
            'duration_min' => $this->durationMin,
        ];
    }
}
