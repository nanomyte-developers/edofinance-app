<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinancialYearStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
      public function rules(): array
    {
        // Get the ID of the financial year being updated from the route parameter
        $yearId = $this->route('financial_year');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                // Ensure the name is unique, ignoring the current year's ID
               // Rule::unique('financial_years', 'name')->ignore($yearId),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ];
    }
}
