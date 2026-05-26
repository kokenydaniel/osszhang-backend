<?php

namespace App\Http\Requests\AIFinance;

use Illuminate\Foundation\Http\FormRequest;

class AiCfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'wallet_id' => 'required|integer|exists:wallets,id',
            'total_balance' => 'required|numeric',
            'locked_savings' => 'required|numeric|min:0',
            'total_pending' => 'required|numeric|min:0',
            'disposable_remaining' => 'required|numeric',
            'overdue_total' => 'required|numeric|min:0',
            'income_received' => 'required|numeric|min:0',
            'spent_this_month' => 'required|numeric|min:0',
            'monthly_balance' => 'required|numeric',
            'total_debts' => 'required|numeric|min:0',
            'top_spending_categories' => 'present|array|max:5',
            'top_spending_categories.*.category' => 'required|string|max:100',
            'top_spending_categories.*.amount' => 'required|numeric|min:0',
            'savings_goals' => 'present|array',
            'savings_goals.*.title' => 'required|string|max:200',
            'savings_goals.*.target_amount' => 'required|numeric|min:0',
            'savings_goals.*.current_amount' => 'required|numeric|min:0',
            'savings_goals.*.remaining_amount' => 'required|numeric|min:0',
            'savings_goals.*.target_date' => 'nullable|date',
            'debts' => 'present|array',
            'debts.*.name' => 'required|string|max:200',
            'debts.*.remaining' => 'required|numeric|min:0',
        ];
    }
}
