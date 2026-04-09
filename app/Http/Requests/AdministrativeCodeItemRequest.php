<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdministrativeCodeItemRequest extends FormRequest
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
        $itemId = $this->route('administrative_code_itemss');

        // dd($this->administrative_code_id);

        return [
            // Ensure the parent administrative_code_id exists and is required
            'administrative_code_id' => ['required', 'integer', 'exists:administrative_codes,id'],

            // Item name is required and unique within its parent economy code
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('administrative_sector_codes')->where(function ($query) {
                    return $query->where('administrative_code_id', $this->administrative_code_id);
                })->ignore($itemId),
            ],

            // Item code is required, string, and unique globally (or within parent if preferred)
            'code' => [
                'required',
                'string',
                'max:50',
                // Assuming 'code' must be unique across all items
                Rule::unique('administrative_sector_codes', 'code')->ignore($itemId),
            ],

            // Status validation: must be 1 or 0
            'status' => ['required', 'integer', Rule::in([1, 0])],
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
                'status' => $status == 1 ? 1 : 0,
            ]);
        }
    }
}
