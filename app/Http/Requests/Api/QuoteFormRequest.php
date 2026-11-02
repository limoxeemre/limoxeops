<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class QuoteFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minPickup = now()->addHours((int) config('transfer.operations.min_lead_time_hours'))->toDateTimeString();

        return [
            ...$this->waypointRules('pickup'),
            ...$this->waypointRules('dropoff'),
            'pickup_at' => ['required_without:flight_time', 'nullable', 'date', 'after_or_equal:'.$minPickup],
            'flight_time' => ['nullable', 'date', 'after:now'],
            'flight_number' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z0-9]{2}\s?\d{1,5}[A-Za-z]?$/'],
            'return_pickup_at' => ['nullable', 'date'],
            'return_flight_time' => ['nullable', 'date', 'after:now'],
            'return_flight_number' => ['nullable', 'string', 'max:15'],
            'adults' => ['required', 'integer', 'min:1', 'max:60'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'infants' => ['nullable', 'integer', 'min:0', 'max:10'],
            'luggage' => ['nullable', 'integer', 'min:0', 'max:60'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'extras' => ['nullable', 'array'],
            'extras.*.id' => ['required', 'integer', 'exists:extras,id'],
            'extras.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }

    protected function waypointRules(string $key): array
    {
        return [
            $key => ['required', 'array'],
            "$key.location_id" => ["required_without:$key.lat", 'nullable', 'integer', 'exists:locations,id'],
            "$key.lat" => ["required_without:$key.location_id", 'nullable', 'numeric', 'between:-90,90'],
            "$key.lng" => ["required_with:$key.lat", 'nullable', 'numeric', 'between:-180,180'],
            "$key.address" => ["required_with:$key.lat", 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'pickup.location_id' => 'alış noktası',
            'dropoff.location_id' => 'bırakış noktası',
            'pickup_at' => 'alış saati',
            'return_pickup_at' => 'dönüş saati',
            'flight_number' => 'uçuş numarası',
        ];
    }
}
