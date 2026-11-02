<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LocationResource;
use App\Http\Resources\VehicleTypeResource;
use App\Http\Resources\ZoneResource;
use App\Models\Extra;
use App\Models\Location;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Geo\ZoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function zones()
    {
        return ZoneResource::collection(Zone::active()->orderBy('name')->get());
    }

    /** GET /locations?search=lara&type=hotel – alış/bırakış autocomplete */
    public function locations(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'zone_id' => ['nullable', 'integer'],
        ]);

        $locations = Location::active()
            ->with('zone')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('address', 'like', "%{$s}%")
                ->orWhere('iata_code', strtoupper($s))))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->zone_id, fn ($q, $z) => $q->where('zone_id', $z))
            ->orderByRaw("CASE WHEN type = 'airport' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->limit(min((int) $request->input('limit', 20), 50))
            ->get();

        return LocationResource::collection($locations);
    }

    public function vehicleTypes()
    {
        return VehicleTypeResource::collection(VehicleType::active()->orderBy('sort_order')->get());
    }

    public function extras(): JsonResponse
    {
        return response()->json([
            'data' => Extra::active()->orderBy('name')->get()->map(fn (Extra $e) => [
                'id' => $e->id,
                'code' => $e->code,
                'name' => $e->name,
                'description' => $e->description,
                'price' => (float) $e->price,
                'per_leg' => $e->per_leg,
                'max_quantity' => $e->max_quantity,
                'currency' => config('transfer.currency'),
            ]),
        ]);
    }

    /** GET /geo/zone?lat=..&lng=.. – koordinatın hizmet bölgesinde olup olmadığı */
    public function resolveZone(Request $request, ZoneResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $zone = $resolver->resolve((float) $data['lat'], (float) $data['lng']);

        return response()->json([
            'data' => [
                'in_service_area' => (bool) $zone,
                'zone' => $zone ? new ZoneResource($zone) : null,
            ],
        ]);
    }
}
