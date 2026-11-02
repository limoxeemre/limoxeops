<?php

namespace App\Http\Resources;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Zone */
class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'center' => ['lat' => $this->center_lat, 'lng' => $this->center_lng],
            'polygon' => $this->when($request->boolean('with_polygon'), $this->polygon),
        ];
    }
}
