<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MdaStoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Set to true assuming authorization is handled by middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the MDA ID from the route parameters if present (for update)
        // This is necessary to ignore the current record's unique fields during an update.
        $mdaId = $this->route('mda') ? $this->route('mda')->id : null;

        return [
            // Full name must be unique, ignoring the current MDA if updating
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('mdas', 'name')->ignore($mdaId),
            ],
            // Code must be unique, ignoring the current MDA if updating
            'code' => [
                'required', 
                'string', 
                'max:200',
                Rule::unique('mdas', 'code')->ignore($mdaId),
            ],
            // Initials must be unique, ignoring the current MDA if updating
            'initials' => [
                'required', 
                'string', 
                'max:200',
                Rule::unique('mdas', 'initials')->ignore($mdaId),
            ],
            
            // Other fields
            'location' => 'nullable|string|max:255',
            'status' => 'required',
            'administrative_code_id' => 'nullable|exists:administrative_codes,id',
            'type' => 'nullable',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The MDA name is required.',
            'code.required' => 'The MDA code is required.',
            'code.unique' => 'This MDA code is already in use.',
            'initials.required' => 'The MDA initials are required.',
            'status.required' => 'The status field is required.',
            // 'type.required' => 'The type field is required.',
            'administrative_code_id.exists' => 'The selected administrative code does not exist.',
        ];
    }
}