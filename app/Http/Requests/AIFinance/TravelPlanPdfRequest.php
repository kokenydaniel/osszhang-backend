<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class TravelPlanPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => 'required|array',
            'plan.destination' => 'required|string|max:200',
            'plan.total_estimated_cost' => 'required|numeric|min:0',
            'plan.daily_itinerary' => 'nullable|array',
            'plan.cost_breakdown' => 'nullable|array',
            'plan.cost_line_items' => 'nullable|array',
            'form' => 'required|array',
            'form.destination' => 'required|string|max:200',
            'form_labels' => 'nullable|array',
            'meta' => 'nullable|array',
        ];
    }
}
