<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Change this to true to allow the request
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year_id' => 'required|exists:financial_years,id',
            'mda_id' => 'required|exists:mdas,id',
            'budget_code_id' => 'required|exists:administrative_sector_codes,id',
            'schedule_number' => 'required|string|max:255',
            'status' => 'required|string|in:Draft,Processed',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.date' => 'required|date',
            'items.*.serial_no' => 'required|string|max:50',
            'items.*.economy_code_id' => 'required|exists:economy_codes,id',
            'items.*.economy_code_item_id' => 'required|exists:economy_code_items,id',
            'items.*.payee_name' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'items.required' => 'At least one line item is required.',
            'items.*.date.required' => 'Date is required for all items.',
            'items.*.economy_code_id.required' => 'Economic Code is required for all items.',
            'items.*.economy_code_item_id.required' => 'Economic Code item is required for all items.',
            'items.*.payee_name.required' => 'Payee name is required for all items.',
            'items.*.amount.required' => 'Amount is required for all items.',
        ];
    }
}