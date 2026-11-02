<?php

namespace App\Http\Resources;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin VehicleType */
class VehicleTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'max_passengers' => $this->max_passengers,
            'max_luggage' => $this->max_luggage,
        ];
    }
}
