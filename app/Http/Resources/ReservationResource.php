<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reservation */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'trip_type' => $this->trip_type->value,
            'agency_reference' => $this->agency_reference,
            'passenger' => [
                'name' => $this->passenger_name,
                'phone' => $this->passenger_phone,
                'email' => $this->passenger_email,
                'country' => $this->passenger_country,
                'adults' => $this->adults,
                'children' => $this->children,
                'infants' => $this->infants,
                'luggage' => $this->luggage,
            ],
            'vehicle_type' => new VehicleTypeResource($this->whenLoaded('vehicleType')),
            'transfers' => TransferResource::collection($this->whenLoaded('transfers')),
            'extras' => $this->whenLoaded('extras', fn () => $this->extras->map(fn ($line) => [
                'code' => $line->extra->code,
                'name' => $line->extra->name,
                'quantity' => $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'total' => (float) $line->total_price,
            ])),
            'price' => [
                'subtotal' => (float) $this->subtotal,
                'discount' => (float) $this->discount,
                'total' => (float) $this->total_price,
                'currency' => $this->currency,
            ],
            'payment' => [
                'status' => $this->payment_status->value,
                'method' => $this->payment_method?->value,
            ],
            'customer_notes' => $this->customer_notes,
            'created_at' => $this->created_at->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
