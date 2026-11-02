<?php

namespace App\Services\Pricing;

use App\Models\Agency;
use App\Services\Geo\Waypoint;
use Carbon\CarbonInterface;

final class QuoteRequest
{
    /**
     * @param  array<int, int>  $extras  extra_id => adet
     */
    public function __construct(
        public readonly Waypoint $pickup,
        public readonly Waypoint $dropoff,
        public readonly CarbonInterface $pickupAt,
        public readonly ?CarbonInterface $returnAt = null,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly int $infants = 0,
        public readonly int $luggage = 0,
        public readonly array $extras = [],
        public readonly ?Agency $agency = null,
        public readonly ?int $vehicleTypeId = null,
    ) {}

    public function passengers(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    public function isRoundTrip(): bool
    {
        return $this->returnAt !== null;
    }
}
