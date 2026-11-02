<?php

namespace App\Services\Pricing;

use App\Enums\PriceSource;
use App\Enums\TransferDirection;
use App\Models\Extra;
use App\Models\PricingRule;
use App\Models\Route;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Geo\Waypoint;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Fiyat hesaplama akışı (her uygun araç tipi için):
 *
 *  1. Alış/bırakış koordinatları → bölge (poligon içinde mi?)
 *  2. Bölge → bölge sabit rota fiyatı (çift yönlü rotalar ters yönde de geçerli)
 *  3. Rota yoksa: araç tipinin km tarifesi (açılış + km × birim, min. ücret)
 *  4. Fiyat kuralları (gece, sezon, hafta sonu, bölge/araç özel) taban fiyata uygulanır
 *  5. Dönüş bacağı aynı mantıkla, kendi tarih/saatine göre hesaplanır
 *  6. Ekstralar (bebek koltuğu vb.) + acente indirimi
 */
class PriceCalculator
{
    private ?Collection $rules = null;

    /** @var array<string, Route|null> */
    private array $routeCache = [];

    public function quote(QuoteRequest $request): Quote
    {
        $distance = $this->roadDistance($request->pickup, $request->dropoff);

        $vehicleTypes = VehicleType::active()
            ->fitting($request->passengers(), $request->luggage)
            ->when($request->vehicleTypeId, fn ($q) => $q->whereKey($request->vehicleTypeId))
            ->orderBy('sort_order')
            ->get();

        $extras = $this->resolveExtras($request->extras);

        $options = $vehicleTypes
            ->map(fn (VehicleType $vt) => $this->quoteVehicle($request, $vt, $distance, $extras))
            ->filter()
            ->sortBy('total')
            ->values();

        $route = $this->findRoute($request->pickup->zone, $request->dropoff->zone);

        return new Quote(
            request: $request,
            options: $options,
            distanceKm: $route?->distance_km ?? $distance,
            durationMin: $route?->duration_min ?? $this->estimateDuration($distance),
        );
    }

    private function quoteVehicle(QuoteRequest $request, VehicleType $vt, float $distance, Collection $extras): ?VehicleQuote
    {
        $legs = [];

        $outbound = $this->quoteLeg(TransferDirection::Outbound, $request->pickup, $request->dropoff, $request->pickupAt, $vt, $distance);
        if (! $outbound) {
            return null; // Bu araç tipi için bu güzergahta fiyat tanımlı değil
        }
        $legs[] = $outbound;

        if ($request->returnAt) {
            $return = $this->quoteLeg(TransferDirection::Return, $request->dropoff, $request->pickup, $request->returnAt, $vt, $distance);
            if (! $return) {
                return null;
            }
            $legs[] = $return;
        }

        $extraLines = $extras->map(function (array $item) use ($legs) {
            /** @var Extra $extra */
            $extra = $item['extra'];
            $multiplier = $extra->per_leg ? count($legs) : 1;
            $unit = (float) $extra->price;

            return [
                'extra_id' => $extra->id,
                'code' => $extra->code,
                'name' => $extra->name,
                'quantity' => $item['quantity'],
                'unit_price' => $unit,
                'legs' => $multiplier,
                'total' => round($unit * $item['quantity'] * $multiplier, 2),
            ];
        })->values()->all();

        $subtotal = round(array_sum(array_map(fn (LegQuote $l) => $l->price, $legs)) + array_sum(array_column($extraLines, 'total')), 2);
        $discount = $request->agency ? round($subtotal * (float) $request->agency->discount_percent / 100, 2) : 0.0;

        return new VehicleQuote(
            vehicleType: $vt,
            legs: $legs,
            extras: $extraLines,
            subtotal: $subtotal,
            discount: $discount,
            total: round($subtotal - $discount, 2),
            currency: config('transfer.currency'),
        );
    }

    public function quoteLeg(
        TransferDirection $direction,
        Waypoint $from,
        Waypoint $to,
        CarbonInterface $pickupAt,
        VehicleType $vt,
        ?float $distance = null,
    ): ?LegQuote {
        $distance ??= $this->roadDistance($from, $to);
        $route = $this->findRoute($from->zone, $to->zone);
        $routePrice = $route?->prices->firstWhere('vehicle_type_id', $vt->id);

        if ($routePrice) {
            $source = PriceSource::FixedRoute;
            $base = (float) $routePrice->price;
        } elseif (config('transfer.pricing.distance_fallback') && $vt->hasDistanceTariff()) {
            $source = PriceSource::Distance;
            $base = max((float) $vt->min_fare, (float) $vt->base_fare + $distance * (float) $vt->per_km_rate);
            $route = null;
        } else {
            return null;
        }

        $zoneIds = array_values(array_filter([$from->zone?->id, $to->zone?->id]));
        $adjustments = $this->rules()
            ->filter(fn (PricingRule $rule) => $rule->appliesTo($pickupAt, $vt->id, $zoneIds))
            ->map(fn (PricingRule $rule) => [
                'rule_id' => $rule->id,
                'name' => $rule->name,
                'amount' => $rule->adjustmentFor($base),
            ])
            ->values()
            ->all();

        $price = max(0, $this->round($base + array_sum(array_column($adjustments, 'amount'))));

        return new LegQuote(
            direction: $direction,
            pickupAt: $pickupAt,
            source: $source,
            basePrice: round($base, 2),
            adjustments: $adjustments,
            price: $price,
            distanceKm: $route?->distance_km ?? $distance,
            durationMin: $route?->duration_min ?? $this->estimateDuration($distance),
            route: $route,
        );
    }

    public function findRoute(?Zone $from, ?Zone $to): ?Route
    {
        if (! $from || ! $to) {
            return null;
        }

        $key = $from->id.'-'.$to->id;

        return $this->routeCache[$key] ??= Route::active()
            ->between($from->id, $to->id)
            ->with('prices')
            ->first();
    }

    public function roadDistance(Waypoint $from, Waypoint $to): float
    {
        return round($from->distanceTo($to) * (float) config('transfer.pricing.road_factor'), 2);
    }

    public function estimateDuration(float $distanceKm): int
    {
        return (int) max(10, ceil($distanceKm / (float) config('transfer.pricing.average_speed_kmh') * 60));
    }

    /**
     * @param  array<int, int>  $requested
     * @return Collection<int, array{extra: Extra, quantity: int}>
     */
    private function resolveExtras(array $requested): Collection
    {
        $requested = array_filter($requested, fn ($qty) => (int) $qty > 0);
        if (! $requested) {
            return collect();
        }

        return Extra::active()->whereIn('id', array_keys($requested))->get()
            ->map(fn (Extra $extra) => [
                'extra' => $extra,
                'quantity' => min((int) $requested[$extra->id], $extra->max_quantity),
            ]);
    }

    private function rules(): Collection
    {
        return $this->rules ??= PricingRule::active()->orderByDesc('priority')->get();
    }

    private function round(float $amount): float
    {
        $step = (float) config('transfer.pricing.rounding_step', 0.01) ?: 0.01;

        return round(ceil(round($amount / $step, 6)) * $step, 2);
    }
}
