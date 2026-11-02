<?php

namespace App\Http\Requests\Api;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends QuoteFormRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'vehicle_type_id' => ['required', 'integer', Rule::exists('vehicle_types', 'id')->where('is_active', true)],
            'passenger_name' => ['required', 'string', 'max:120'],
            'passenger_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,25}$/'],
            'passenger_email' => ['required', 'email:rfc', 'max:190'],
            'passenger_country' => ['nullable', 'string', 'size:2'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'agency_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
