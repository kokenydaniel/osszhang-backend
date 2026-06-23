<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TravelPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => 'required|string|min:2|max:200',
            'origin_location' => 'nullable|string|min:2|max:200',
            'duration_days' => 'required|integer|min:1|max:90',
            'total_budget' => 'required|numeric|min:1000|max:999999999',
            'target_date' => 'nullable|date|after_or_equal:today',
            'travelers_count' => 'sometimes|integer|min:1|max:12',
            'trip_style' => ['sometimes', Rule::in(['beach', 'city', 'adventure', 'domestic', 'mixed'])],
            'accommodation_preference' => ['sometimes', Rule::in(['hostel', 'hotel', 'apartment', 'mixed'])],
            'transport_mode' => ['sometimes', Rule::in(['car', 'plane', 'train', 'bus', 'mixed'])],
            'transport_already_booked' => 'sometimes|boolean',
            'accommodation_already_booked' => 'sometimes|boolean',
            'car_fuel_consumption_l100' => 'nullable|numeric|min:3|max:25',
            'wallet_id' => 'sometimes|integer|exists:wallets,id',
            'compare_budgets' => 'sometimes|boolean',
            'exchange_rates' => 'sometimes|array',
            'exchange_rates.*' => 'numeric|min:0',
        ];
    }
}
