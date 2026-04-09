<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EconomyCodeItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Assuming authorization is handled elsewhere (e.g., policy or middleware),
        // or for simplicity, we allow authorized users to proceed.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the current model instance for updates to ignore the unique constraint on itself
        $itemId = $this->route('economy_code_itemss');

        // dd($itemId);

        return [
            // Ensure the parent economy_code_id exists and is required
            'economy_code_id' => ['required', 'integer', 
            'exists:economy_codes,id'
            // Rule::unique('economy_codes', 'code')->ignore($recordId)
        
        ],

            // Item name is required and unique within its parent economy code
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('economy_code_items')->where(function ($query) {
                    return $query->where('economy_code_id', $this->economy_code_id);
                })->ignore($itemId),
            ],

            // Item code is required, string, and unique globally (or within parent if preferred)
            'code' => [
                'required',
                'string',
                'max:50',
                // Assuming 'code' must be unique across all items
                Rule::unique('economy_code_items', 'code')->ignore($itemId),
            ],

            // Status validation: must be 'active' or 'inactive'
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Converts status (1/0) from the form into string ('active'/'inactive').
     * If the input is already a string, it is used as is.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');

        if (is_numeric($status)) {
            $this->merge([
                'status' => $status == 1 ? 'active' : 'inactive',
            ]);
        }
    }
}
