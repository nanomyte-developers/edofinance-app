<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectorStoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming authorization is handled by middleware or policy, return true for now.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the Sector ID if we are performing an update operation
        $sectorId = $this->route('sector') ? $this->route('sector')->id : null;

        return [
            'mda_id' => [
                'required', 
                'integer', 
                'exists:mdas,id', // Ensure the MDA ID exists in the MDAs table
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                // Ensure name is unique, ignoring the current sector ID if editing
                Rule::unique('sectors', 'name')->ignore($sectorId),
            ],
            'code' => [
                'required',
                'string',
                'max:10',
                // Ensure code is unique, ignoring the current sector ID if editing
                Rule::unique('sectors', 'code')->ignore($sectorId),
            ],
            // --- NEW FIELDS ---
            'initials' => [ // New field: Requires unique initials
                'required',
                'string',
                'max:10',
                Rule::unique('sectors', 'initials')->ignore($sectorId),
            ],
            'location' => [ // New field: Can be nullable
                'nullable', 
                'string',
                'max:255',
            ],
            'status' => [ // New field: Required and constrained to specific values
                'required',
            ],
            // --- END NEW FIELDS ---
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Ensures mda_id is treated as an integer if it comes from the form as a string.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'mda_id' => (int) $this->mda_id,
        ]);
    }
}