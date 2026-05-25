<?php

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHouseholdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'manual_balance' => 'sometimes|numeric',
            'budget_enabled' => 'sometimes|boolean',
            'savings_enabled' => 'sometimes|boolean',
            'debts_enabled' => 'sometimes|boolean',
            'utilities_enabled' => 'sometimes|boolean',
            'meters_enabled' => 'sometimes|boolean',
            'onboarding_completed' => 'sometimes|boolean',
            'business_enabled' => 'sometimes|boolean',
            'business_name' => 'sometimes|string|max:255',
            'shopify_import_enabled' => 'sometimes|boolean',
            'shopify_shop_url' => 'sometimes|nullable|string|max:255',
            'shopify_access_token' => 'sometimes|nullable|string|max:4096',
            'utility_split_enabled' => 'sometimes|boolean',
            'utility_split_partner_id' => 'sometimes|nullable|integer|exists:users,id',
            'business_settings' => 'sometimes|array',
            'business_settings.channels' => 'sometimes|array',
            'business_settings.channels.*' => 'string|max:100',
            'business_settings.payment_methods' => 'sometimes|array',
            'business_settings.payment_methods.*' => 'string|max:100',
            'business_settings.providers' => 'sometimes|array',
            'business_settings.providers.*' => 'string|max:100',
            'business_settings.destinations' => 'sometimes|array',
            'business_settings.destinations.*' => 'string|max:100',
            'utility_templates' => 'sometimes|array',
            'utility_templates.*.type' => 'required|string|max:100',
            'utility_templates.*.total' => 'sometimes|numeric|min:0',
            'utility_templates.*.due_day' => 'sometimes|integer|min:1|max:28',
            'utility_templates.*.split_rule' => 'sometimes|in:shared,dani-private,ildi-private',
            'savings_settings' => 'sometimes|array',
            'savings_settings.owners' => 'sometimes|array',
            'savings_settings.owners.*' => 'string|max:100',
            'savings_settings.default_owner' => 'sometimes|nullable|string|max:100',
            'savings_settings.separate_owner' => 'sometimes|nullable|string|max:100',
            'savings_settings.currencies' => 'sometimes|array',
            'savings_settings.currencies.*' => 'string|max:10',
            'savings_settings.default_count_in_savings' => 'sometimes|boolean',
            'debts_settings' => 'sometimes|array',
            'debts_settings.default_strategy' => 'sometimes|in:avalanche,snowball',
            'debts_settings.default_extra_monthly' => 'sometimes|integer|min:0',
            'debts_settings.pay_add_to_budget_default' => 'sometimes|boolean',
            'debts_settings.payment_category_pattern' => 'sometimes|string|max:100',
            'meters_settings' => 'sometimes|array',
            'meters_settings.default_location' => 'sometimes|string|max:100',
            'meters_settings.units' => 'sometimes|array|min:1',
            'meters_settings.units.*' => 'string|max:20',
            'meters_settings.templates' => 'sometimes|array',
            'meters_settings.templates.*.name' => 'required|string|max:100',
            'meters_settings.templates.*.unit' => 'sometimes|string|max:20',
            'meters_settings.templates.*.location' => 'sometimes|string|max:100',
        ];
    }
}
