<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles validation for creating a new EconomyCode record.
 *
 * NOTE: The namespace was changed from 'App\Http\Requests\EconomyCode' to
 * 'App\Http\Requests' to match the file's location in 'app/Http/Requests/'.
 */
class EconomyCodeStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming authorization is handled by middleware (e.g., 'auth' or 'role')
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
            // Name: Required, max 255 chars
            'name' => ['required', 'string', 'max:255'],

            // Code: Required, max 20 chars, must be unique in the economy_codes table
            'code' => ['required', 'string', 'max:20', Rule::unique('economy_codes', 'code')],

            // Status: Optional, defaults to 'active', must be one of the enum values
            //'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
